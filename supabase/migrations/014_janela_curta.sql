-- Lista sem data não pode mais sair inteira. Sem intervalo, vale o mês corrente.
-- A leitura completa (fln_ler / fln_ler_leve) entra na mesma cota da lista.

create or replace function public.fln_ler_menu(
  p_segredo text,
  p_base smallint,
  p_modo text,
  p_de text,
  p_ate text,
  p_id integer,
  p_vista bigint default 0
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
  hoje date;
begin
  perform fln_privado.conferir(p_segredo);
  p_modo := coalesce(p_modo, 'corridas');

  select e.versao into versao
    from fln_privado.estado e
   where e.id = p_base;
  if versao is null then
    return jsonb_build_object('versao', 0, 'dados', null);
  end if;

  if coalesce(p_vista, 0) > 0 and p_vista = versao then
    return jsonb_build_object('versao', versao, 'igual', true);
  end if;

  if p_modo in ('corridas', 'financeiro')
     and not fln_privado.cota_permite('lista', 60) then
    raise exception 'cota de leitura da lista' using errcode = '53400';
  end if;

  if p_modo = 'uma' and not fln_privado.cota_permite('uma', 120) then
    raise exception 'cota de leitura da reserva' using errcode = '53400';
  end if;

  if p_modo in ('corridas', 'financeiro') then
    hoje := (pg_catalog.now() at time zone 'America/Sao_Paulo')::date;
    if p_de is null or p_ate is null
       or p_de !~ '^\d{4}-\d{2}-\d{2}$'
       or p_ate !~ '^\d{4}-\d{2}-\d{2}$'
       or p_de > p_ate then
      p_de := to_char(date_trunc('month', hoje)::date, 'YYYY-MM-DD');
      p_ate := to_char((date_trunc('month', hoje) + interval '1 month' - interval '1 day')::date, 'YYYY-MM-DD');
    end if;
  end if;

  if p_modo = 'casca' then
    select (e.dados - 'transfers' - 'lancamentos') #- '{chat,mensagens}'
      into doc
      from fln_privado.estado e
     where e.id = p_base;
    doc := doc || jsonb_build_object('_menu', 'casca');
    return jsonb_build_object('versao', versao, 'dados', doc);
  end if;

  if p_modo = 'caixa' then
    select (e.dados - 'transfers' - 'lancamentos') #- '{chat,mensagens}', e.lancamentos_doc
      into doc, lanc
      from fln_privado.estado e
     where e.id = p_base;
    doc := jsonb_set(doc, '{lancamentos}', coalesce(lanc, '[]'::jsonb), true);
    doc := doc || jsonb_build_object('_menu', 'caixa');
    return jsonb_build_object('versao', versao, 'dados', doc);
  end if;

  select (e.dados - 'transfers' - 'lancamentos') #- '{chat,mensagens}', e.transfers_doc, e.lancamentos_doc
    into doc, trips, lanc
    from fln_privado.estado e
   where e.id = p_base;
  trips := coalesce(trips, doc -> 'transfers', '[]'::jsonb);

  if p_modo = 'uma' then
    select t.elem into uma
      from jsonb_array_elements(trips) as t(elem)
     where (t.elem->>'id')::int = coalesce(p_id, 0)
     limit 1;
    doc := jsonb_set(doc, '{transfers}', case when uma is null then '[]'::jsonb else jsonb_build_array(uma) end, true);
    doc := doc || jsonb_build_object('_menu', 'uma');
    return jsonb_build_object('versao', versao, 'dados', doc);
  end if;

  doc := jsonb_set(doc, '{transfers}', fln_privado.transfers_colunar(trips, coalesce(p_de, ''), coalesce(p_ate, '')), true);
  if p_modo = 'financeiro' then
    doc := jsonb_set(doc, '{lancamentos}', coalesce(lanc, doc -> 'lancamentos', '[]'::jsonb), true);
  end if;
  doc := doc || jsonb_build_object('_menu', case when p_modo = 'financeiro' then 'financeiro' else 'corridas' end);
  return jsonb_build_object('versao', versao, 'dados', doc);
end
$function$;

create or replace function public.fln_ler(p_segredo text, p_base smallint default 1)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  r record;
  doc jsonb;
begin
  perform fln_privado.conferir(p_segredo);
  if not fln_privado.cota_permite('lista', 60) then
    raise exception 'cota de leitura da lista' using errcode = '53400';
  end if;
  select versao, dados, transfers_doc, lancamentos_doc into r
    from fln_privado.estado where id = p_base;
  if not found then
    return jsonb_build_object('versao', 0, 'dados', null);
  end if;
  doc := r.dados;
  doc := jsonb_set(doc, '{transfers}', coalesce(r.transfers_doc, doc -> 'transfers', '[]'::jsonb), true);
  doc := jsonb_set(doc, '{lancamentos}', coalesce(r.lancamentos_doc, doc -> 'lancamentos', '[]'::jsonb), true);
  return jsonb_build_object('versao', r.versao, 'dados', doc);
end
$function$;

create or replace function public.fln_ler_leve(p_segredo text, p_base smallint default 1)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  r record;
  doc jsonb;
begin
  perform fln_privado.conferir(p_segredo);
  if not fln_privado.cota_permite('lista', 60) then
    raise exception 'cota de leitura da lista' using errcode = '53400';
  end if;
  select versao, dados, transfers_doc, lancamentos_doc into r
    from fln_privado.estado where id = p_base;
  if not found then
    return jsonb_build_object('versao', 0, 'dados', null);
  end if;
  doc := r.dados #- '{chat,mensagens}';
  doc := jsonb_set(doc, '{transfers}', coalesce(r.transfers_doc, doc -> 'transfers', '[]'::jsonb), true);
  doc := jsonb_set(doc, '{lancamentos}', coalesce(r.lancamentos_doc, doc -> 'lancamentos', '[]'::jsonb), true);
  return jsonb_build_object('versao', r.versao, 'dados', doc);
end
$function$;

notify pgrst, 'reload schema';
