-- Uma leitura pesada por versão. Repetir a mesma lista não sai do banco.
-- No máximo 40 listas grandes por hora, mesmo que quem lê ignore a versão.

create table if not exists fln_privado.cota_saida (
  chave text primary key,
  hora timestamptz not null,
  n integer not null
);

revoke all on table fln_privado.cota_saida from public, anon, authenticated;

create or replace function fln_privado.cota_permite(p_chave text, p_limite integer)
returns boolean
language plpgsql
security definer
set search_path to ''
as $function$
declare
  agora timestamptz := date_trunc('hour', pg_catalog.now());
  atual integer;
begin
  insert into fln_privado.cota_saida as c (chave, hora, n)
  values (p_chave, agora, 1)
  on conflict (chave) do update
    set n = case when c.hora = agora then c.n + 1 else 1 end,
        hora = agora
  returning n into atual;
  return atual <= p_limite;
end
$function$;

revoke all on function fln_privado.cota_permite(text, integer) from public, anon, authenticated;

drop function if exists public.fln_ler_menu(text, smallint, text, text, text, integer);

create function public.fln_ler_menu(
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
begin
  perform fln_privado.conferir(p_segredo);
  p_modo := coalesce(p_modo, 'corridas');

  select e.versao into versao
    from fln_privado.estado e
   where e.id = p_base;
  if versao is null then
    return jsonb_build_object('versao', 0, 'dados', null);
  end if;

  -- Quem já tem esta versão não recebe as reservas de novo.
  if coalesce(p_vista, 0) > 0 and p_vista = versao then
    return jsonb_build_object('versao', versao, 'igual', true);
  end if;

  -- Lista inteira ou o financeiro: teto por hora. Recusa em vez de devolver vazio.
  if p_modo in ('corridas', 'financeiro')
     and not fln_privado.cota_permite('lista', 20) then
    raise exception 'cota de leitura da lista' using errcode = '53400';
  end if;

  if p_modo = 'uma' and not fln_privado.cota_permite('uma', 120) then
    raise exception 'cota de leitura da reserva' using errcode = '53400';
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

revoke all on function public.fln_ler_menu(text, smallint, text, text, text, integer, bigint) from public;
grant execute on function public.fln_ler_menu(text, smallint, text, text, text, integer, bigint) to anon, authenticated, service_role;

notify pgrst, 'reload schema';
