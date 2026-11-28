-- A gravação reescrevia o documento inteiro uma vez por reserva e uma vez
-- por lançamento. Com milhares de viagens isso passava do tempo e a API
-- respondia 500 (SQLSTATE 57014). Agora cada lote entra numa passagem só.
-- O resultado é o mesmo: atualiza pelo número, acrescenta o que não existia,
-- e mantém a ordem anterior.

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
) returns bigint
language plpgsql
security definer
set search_path to ''
as $function$
declare
  atual jsonb;
  trips jsonb;
  lanc jsonb;
  novos jsonb;
  v bigint;
  item jsonb;
begin
  perform set_config('statement_timeout', '90s', true);
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

  if not pg_catalog.jsonb_exists(p_dados, 'chat') then
    p_dados := jsonb_set(p_dados, array['chat'], coalesce(atual -> 'chat', '{}'::jsonb), true);
  elsif not pg_catalog.jsonb_exists(p_dados -> 'chat', 'mensagens') then
    p_dados := jsonb_set(p_dados, array['chat', 'mensagens'], coalesce(atual -> 'chat' -> 'mensagens', '{}'::jsonb), true);
  end if;
  if jsonb_typeof(atual -> 'mensagens') = 'array'
     and jsonb_array_length(atual -> 'mensagens') > 0
     and (
       not pg_catalog.jsonb_exists(p_dados, 'mensagens')
       or jsonb_typeof(p_dados -> 'mensagens') <> 'array'
       or jsonb_array_length(p_dados -> 'mensagens') = 0
     ) then
    p_dados := jsonb_set(p_dados, '{mensagens}', atual -> 'mensagens', true);
  end if;

  p_dados := p_dados - 'transfers' - 'lancamentos' - '_menu';
  p_trips := fln_privado.sem_obs(p_base, jsonb_build_object('transfers', coalesce(p_trips, '[]'::jsonb))) -> 'transfers';

  novos := '{}'::jsonb;
  for item in select jsonb_array_elements(coalesce(p_trips, '[]'::jsonb))
  loop
    if coalesce((item ->> 'id')::int, 0) <= 0 then
      raise exception 'reserva sem número' using errcode = '22023';
    end if;
    novos := novos || jsonb_build_object(item ->> 'id', item);
  end loop;

  select coalesce(jsonb_agg(
           case
             when pg_catalog.jsonb_exists(novos, t.elem ->> 'id') then novos -> (t.elem ->> 'id')
             else t.elem
           end
           order by t.ord
         ), '[]'::jsonb)
    into trips
    from jsonb_array_elements(trips) with ordinality as t(elem, ord);

  select trips || coalesce(jsonb_agg(s.item order by s.ord), '[]'::jsonb)
    into trips
    from jsonb_array_elements(coalesce(p_trips, '[]'::jsonb)) with ordinality as s(item, ord)
   where not exists (
     select 1
       from jsonb_array_elements(trips) as t(elem)
      where (t.elem ->> 'id') = (s.item ->> 'id')
   );

  if p_lancamentos is not null and jsonb_typeof(p_lancamentos) = 'array' then
    novos := '{}'::jsonb;
    for item in select jsonb_array_elements(p_lancamentos)
    loop
      if coalesce((item ->> 'id')::int, 0) <= 0 then
        continue;
      end if;
      if coalesce(item ->> 'papel', '') in ('_teto', '_eco') then
        continue;
      end if;
      novos := novos || jsonb_build_object(item ->> 'id', item);
    end loop;

    select coalesce(jsonb_agg(
             case
               when pg_catalog.jsonb_exists(novos, l.elem ->> 'id') then novos -> (l.elem ->> 'id')
               else l.elem
             end
             order by l.ord
           ), '[]'::jsonb)
      into lanc
      from jsonb_array_elements(lanc) with ordinality as l(elem, ord);

    select lanc || coalesce(jsonb_agg(s.item order by s.ord), '[]'::jsonb)
      into lanc
      from jsonb_array_elements(p_lancamentos) with ordinality as s(item, ord)
     where coalesce((s.item ->> 'id')::int, 0) > 0
       and coalesce(s.item ->> 'papel', '') not in ('_teto', '_eco')
       and not exists (
         select 1
           from jsonb_array_elements(lanc) as l(elem)
          where (l.elem ->> 'id') = (s.item ->> 'id')
       );
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
