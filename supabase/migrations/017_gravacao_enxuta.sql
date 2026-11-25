-- Gravação de reserva sem devolver as 3 mil viagens.
-- O financeiro de um mês também deixa de trazer todos os lançamentos.

create or replace function public.fln_ler_gravacao(
  p_segredo text,
  p_base smallint,
  p_id integer,
  p_de text,
  p_ate text
)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  versao bigint;
  doc jsonb;
  trips jsonb;
  lanc jsonb;
  uma jsonb;
  agenda jsonb;
  alvo_de date;
  alvo_ate date;
  max_transfer int;
  max_lancamento int;
begin
  perform fln_privado.conferir(p_segredo);
  if not fln_privado.cota_permite('gravacao', 120) then
    raise exception 'cota de leitura da gravação' using errcode = '53400';
  end if;
  if p_base not in (1, 2) then
    raise exception 'base inválida' using errcode = '22023';
  end if;
  if p_de is null or p_ate is null
     or p_de !~ '^\d{4}-\d{2}-\d{2}$'
     or p_ate !~ '^\d{4}-\d{2}-\d{2}$'
     or p_de > p_ate then
    raise exception 'intervalo inválido' using errcode = '22023';
  end if;

  select e.versao,
         (e.dados - 'transfers' - 'lancamentos') #- '{chat,mensagens}' #- '{mensagens}',
         e.transfers_doc,
         e.lancamentos_doc
    into versao, doc, trips, lanc
    from fln_privado.estado e
   where e.id = p_base;
  if versao is null then
    return jsonb_build_object('versao', 0, 'dados', null);
  end if;
  trips := coalesce(trips, '[]'::jsonb);
  lanc := coalesce(lanc, '[]'::jsonb);
  alvo_de := p_de::date;
  alvo_ate := p_ate::date;

  if coalesce(p_id, 0) > 0 then
    select t.elem into uma
      from jsonb_array_elements(trips) as t(elem)
     where coalesce(t.elem->>'id', '') ~ '^\d+$'
       and (t.elem->>'id')::int = p_id
     limit 1;
    if uma is not null and (uma->>'data') ~ '^\d{4}-\d{2}-\d{2}$' then
      alvo_de := least(alvo_de, (uma->>'data')::date - 2);
      alvo_ate := greatest(alvo_ate, (uma->>'data')::date + 2);
    end if;
    if uma is not null and (uma->>'data_volta') ~ '^\d{4}-\d{2}-\d{2}$' then
      alvo_de := least(alvo_de, (uma->>'data_volta')::date - 2);
      alvo_ate := greatest(alvo_ate, (uma->>'data_volta')::date + 2);
    end if;
  end if;

  select coalesce(jsonb_agg(jsonb_build_object(
           'id', t.elem->'id',
           'data', t.elem->>'data',
           'hora', t.elem->>'hora',
           'data_volta', t.elem->>'data_volta',
           'hora_volta', t.elem->>'hora_volta',
           'tipo', t.elem->>'tipo',
           'status', t.elem->>'status',
           'qtd_horas', t.elem->>'qtd_horas',
           'motorista_id', t.elem->'motorista_id',
           'motorista_volta_id', t.elem->'motorista_volta_id',
           'extras', coalesce(t.elem->'extras', '[]'::jsonb),
           'conjunto', t.elem->>'conjunto',
           'ordem', t.elem->'ordem',
           'trajetos', case when t.elem->>'tipo' = 'trajetos' then coalesce(t.elem->'trajetos', '[]'::jsonb) else '[]'::jsonb end
         )), '[]'::jsonb)
    into agenda
    from jsonb_array_elements(trips) as t(elem)
   where coalesce(t.elem->>'status', '') <> 'cancelada'
     and (coalesce(p_id, 0) <= 0 or coalesce(t.elem->>'id', '') !~ '^\d+$' or (t.elem->>'id')::int <> p_id)
     and (
       ((t.elem->>'data') ~ '^\d{4}-\d{2}-\d{2}$' and (t.elem->>'data')::date between alvo_de and alvo_ate)
       or ((t.elem->>'data_volta') ~ '^\d{4}-\d{2}-\d{2}$' and (t.elem->>'data_volta')::date between alvo_de and alvo_ate)
     );

  select coalesce(max((t.elem->>'id')::int), 0)
    into max_transfer
    from jsonb_array_elements(trips) as t(elem)
   where coalesce(t.elem->>'id', '') ~ '^\d+$';
  select coalesce(max((l.elem->>'id')::int), 0)
    into max_lancamento
    from jsonb_array_elements(lanc) as l(elem)
   where coalesce(l.elem->>'id', '') ~ '^\d+$';

  if coalesce(p_id, 0) > 0 then
    select coalesce(jsonb_agg(l.elem), '[]'::jsonb)
      into lanc
      from jsonb_array_elements(lanc) as l(elem)
     where coalesce(l.elem->>'transfer_id', '') ~ '^\d+$'
       and (l.elem->>'transfer_id')::int = p_id;
  else
    lanc := '[]'::jsonb;
  end if;

  return jsonb_build_object(
    'versao', versao,
    'dados', doc,
    'trip', uma,
    'lancamentos', coalesce(lanc, '[]'::jsonb),
    'agenda', coalesce(agenda, '[]'::jsonb),
    'max_transfer', max_transfer,
    'max_lancamento', max_lancamento
  );
end
$function$;

revoke all on function public.fln_ler_gravacao(text, smallint, integer, text, text) from public;
grant execute on function public.fln_ler_gravacao(text, smallint, integer, text, text) to anon, authenticated, service_role;

create or replace function public.fln_gravar_reservas(
  p_segredo text,
  p_versao bigint,
  p_dados jsonb,
  p_trips jsonb,
  p_lancamentos jsonb,
  p_acao text,
  p_usuario text,
  p_base smallint default 1
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
  if p_dados is null or jsonb_typeof(p_dados) <> 'object'
     or jsonb_typeof(p_dados -> 'motoristas') <> 'array'
     or jsonb_typeof(p_dados -> 'settings') <> 'object'
     or jsonb_typeof(p_trips) <> 'array'
     or jsonb_array_length(p_trips) < 1
     or jsonb_array_length(p_trips) > 40 then
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
  p_trips := fln_privado.sem_obs(p_base, jsonb_build_object('transfers', p_trips)) -> 'transfers';

  for item in select jsonb_array_elements(p_trips)
  loop
    if coalesce((item ->> 'id')::int, 0) <= 0 then
      raise exception 'reserva sem número' using errcode = '22023';
    end if;
    achou := false;
    n := jsonb_array_length(trips);
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
      n := jsonb_array_length(lanc);
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
      'gravadas', jsonb_array_length(p_trips)
    )
  );
  return v;
end
$function$;

revoke all on function public.fln_gravar_reservas(text, bigint, jsonb, jsonb, jsonb, text, text, smallint) from public;
grant execute on function public.fln_gravar_reservas(text, bigint, jsonb, jsonb, jsonb, text, text, smallint) to anon, authenticated, service_role;

do $mig$
declare
  src text;
  antigo text := $old$
  if p_modo = 'financeiro' then
    doc := jsonb_set(doc, '{lancamentos}', coalesce(lanc, doc -> 'lancamentos', '[]'::jsonb), true);
  end if;
$old$;
  novo text := $novo$
  if p_modo = 'financeiro'
     and (p_ate::date - p_de::date) between 20 and 62 then
    select coalesce(jsonb_agg(l.elem), '[]'::jsonb)
      into lanc
      from jsonb_array_elements(coalesce(lanc, '[]'::jsonb)) as l(elem)
     where coalesce(l.elem->>'data', '') between p_de and p_ate
        or coalesce(l.elem->>'transfer_id', '') in (
          select t.elem->>'id'
            from jsonb_array_elements(trips) as t(elem)
           where coalesce(t.elem->>'data', '') between p_de and p_ate
              or coalesce(t.elem->>'data_volta', '') between p_de and p_ate
        );
  end if;
  if p_modo = 'financeiro' then
    doc := jsonb_set(doc, '{lancamentos}', coalesce(lanc, '[]'::jsonb), true);
  end if;
$novo$;
begin
  src := pg_get_functiondef('public.fln_ler_menu(text,smallint,text,text,text,integer,bigint)'::regprocedure);
  if position('between 20 and 62' in src) = 0 then
    if position(antigo in src) = 0 then
      raise exception 'ponto do financeiro não encontrado';
    end if;
    execute replace(src, antigo, novo);
  end if;
end
$mig$;

notify pgrst, 'reload schema';
