-- Cancelar uma reserva não pode baixar a lista inteira.
-- A gravação enxuta passa a remover só o número pedido.

drop function if exists public.fln_gravar_reservas(text, bigint, jsonb, jsonb, jsonb, text, text, smallint);

create or replace function public.fln_gravar_reservas(
  p_segredo text,
  p_versao bigint,
  p_dados jsonb,
  p_trips jsonb,
  p_lancamentos jsonb,
  p_acao text,
  p_usuario text,
  p_base smallint default 1,
  p_apagar jsonb default '[]'::jsonb
)
returns bigint
language plpgsql
security definer
set search_path to ''
as $function$
declare
  atual jsonb;
  trips jsonb;
  lanc jsonb;
  v bigint;
  item jsonb;
  i int;
  n int;
  achou boolean;
begin
  perform set_config('statement_timeout', '60s', true);
  perform fln_privado.conferir(p_segredo);
  if p_base not in (1, 2) then
    raise exception 'base inválida' using errcode = '22023';
  end if;
  p_apagar := coalesce(p_apagar, '[]'::jsonb);
  if p_dados is null or jsonb_typeof(p_dados) <> 'object'
     or jsonb_typeof(p_dados -> 'motoristas') <> 'array'
     or jsonb_typeof(p_dados -> 'settings') <> 'object'
     or jsonb_typeof(p_trips) <> 'array'
     or jsonb_typeof(p_apagar) <> 'array'
     or jsonb_array_length(p_trips) > 40
     or jsonb_array_length(p_apagar) > 3
     or (jsonb_array_length(p_trips) < 1 and jsonb_array_length(p_apagar) < 1) then
    raise exception 'estado inválido' using errcode = '22023';
  end if;

  select dados, transfers_doc, lancamentos_doc
    into atual, trips, lanc
    from fln_privado.estado
   where id = p_base and versao = p_versao
   for update;
  if not found then
    return -1;
  end if;
  trips := coalesce(trips, '[]'::jsonb);
  lanc := coalesce(lanc, '[]'::jsonb);

  if jsonb_array_length(p_apagar) > 0 then
    if exists (
      select 1
        from jsonb_array_elements(trips) as t(elem)
       where (t.elem->>'id') in (select jsonb_array_elements_text(p_apagar))
         and coalesce(t.elem->>'pago', '') in ('true', 't')
    ) then
      raise exception 'reserva paga' using errcode = '22023';
    end if;
    select coalesce(jsonb_agg(t.elem), '[]'::jsonb)
      into trips
      from jsonb_array_elements(trips) as t(elem)
     where coalesce(t.elem->>'id', '') = ''
        or (t.elem->>'id') not in (select jsonb_array_elements_text(p_apagar));
    select coalesce(jsonb_agg(l.elem), '[]'::jsonb)
      into lanc
      from jsonb_array_elements(lanc) as l(elem)
     where coalesce(l.elem->>'transfer_id', '') = ''
        or (l.elem->>'transfer_id') not in (select jsonb_array_elements_text(p_apagar))
        or coalesce(l.elem->>'status', '') = 'pago';
  end if;

  if not (p_dados ? 'chat') then
    p_dados := jsonb_set(p_dados, array['chat'], coalesce(atual -> 'chat', '{}'::jsonb), true);
  elsif not (p_dados -> 'chat' ? 'mensagens') then
    p_dados := jsonb_set(p_dados, array['chat', 'mensagens'], coalesce(atual -> 'chat' -> 'mensagens', '{}'::jsonb), true);
  end if;
  if jsonb_typeof(atual -> 'mensagens') = 'array'
     and jsonb_array_length(atual -> 'mensagens') > 0
     and (
       not (p_dados ? 'mensagens')
       or jsonb_typeof(p_dados -> 'mensagens') <> 'array'
       or jsonb_array_length(p_dados -> 'mensagens') = 0
     ) then
    p_dados := jsonb_set(p_dados, '{mensagens}', atual -> 'mensagens', true);
  end if;

  p_dados := p_dados - 'transfers' - 'lancamentos' - '_menu';
  p_trips := fln_privado.sem_obs(p_base, jsonb_build_object('transfers', coalesce(p_trips, '[]'::jsonb))) -> 'transfers';

  for item in select jsonb_array_elements(coalesce(p_trips, '[]'::jsonb))
  loop
    if coalesce((item ->> 'id')::int, 0) <= 0 then
      raise exception 'reserva sem número' using errcode = '22023';
    end if;
    achou := false;
    n := coalesce(jsonb_array_length(trips), 0);
    for i in 0 .. n - 1 loop
      if (trips -> i ->> 'id') = (item ->> 'id') then
        trips := jsonb_set(trips, array[i::text], item, false);
        achou := true;
        exit;
      end if;
    end loop;
    if not achou then
      trips := trips || jsonb_build_array(item);
    end if;
  end loop;

  if p_lancamentos is not null and jsonb_typeof(p_lancamentos) = 'array' then
    for item in select jsonb_array_elements(p_lancamentos)
    loop
      if coalesce((item ->> 'id')::int, 0) <= 0 then
        continue;
      end if;
      if coalesce(item ->> 'papel', '') in ('_teto', '_eco') then
        continue;
      end if;
      achou := false;
      n := coalesce(jsonb_array_length(lanc), 0);
      for i in 0 .. n - 1 loop
        if (lanc -> i ->> 'id') = (item ->> 'id') then
          lanc := jsonb_set(lanc, array[i::text], item, false);
          achou := true;
          exit;
        end if;
      end loop;
      if not achou then
        lanc := lanc || jsonb_build_array(item);
      end if;
    end loop;
  end if;

  update fln_privado.estado
     set versao = versao + 1,
         dados = p_dados,
         transfers_doc = trips,
         lancamentos_doc = lanc,
         aviso_cache = coalesce(p_dados -> 'avisos', '[]'::jsonb),
         chat_cache = fln_privado.chat_cache(p_dados),
         atualizado_em = pg_catalog.now(),
         atualizado_por = left(coalesce(p_usuario, ''), 80)
   where id = p_base
  returning versao into v;

  insert into fln_privado.historico (base, versao, acao, usuario, dados)
  values (
    p_base, v, left(coalesce(p_acao, ''), 60), left(coalesce(p_usuario, ''), 80),
    jsonb_build_object(
      'transfers', jsonb_array_length(trips),
      'lancamentos', jsonb_array_length(lanc),
      'gravadas', jsonb_array_length(p_trips),
      'apagadas', jsonb_array_length(p_apagar)
    )
  );
  return v;
end
$function$;

revoke all on function public.fln_gravar_reservas(text, bigint, jsonb, jsonb, jsonb, text, text, smallint, jsonb) from public;
grant execute on function public.fln_gravar_reservas(text, bigint, jsonb, jsonb, jsonb, text, text, smallint, jsonb) to anon, authenticated, service_role;

notify pgrst, 'reload schema';
