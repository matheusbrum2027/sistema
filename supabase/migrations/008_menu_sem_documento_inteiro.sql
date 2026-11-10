-- Separa reservas e lançamentos do documento lido em todo menu.
-- A agenda pede só a janela do mês. Configurações e cadastros não baixam as corridas.

alter table fln_privado.estado
  add column if not exists transfers_doc jsonb,
  add column if not exists lancamentos_doc jsonb;

update fln_privado.estado
   set transfers_doc = coalesce(transfers_doc, dados -> 'transfers'),
       lancamentos_doc = coalesce(lancamentos_doc, dados -> 'lancamentos');

do $chk$
declare
  qtd int;
  lanc int;
  menor int;
  maior int;
begin
  select jsonb_array_length(transfers_doc), jsonb_array_length(lancamentos_doc)
    into qtd, lanc
    from fln_privado.estado where id = 1;
  select min((t->>'id')::int), max((t->>'id')::int)
    into menor, maior
    from fln_privado.estado e, lateral jsonb_array_elements(e.transfers_doc) t
   where e.id = 1;
  if qtd <> 2805 or lanc <> 2908 or menor <> 1007 or maior <> 4083 then
    raise exception 'separação inválida: reservas % lancamentos % ids %..%', qtd, lanc, menor, maior;
  end if;
end
$chk$;

create or replace function fln_privado.transfers_colunar(p_doc jsonb, p_de text, p_ate text)
returns jsonb
language sql
stable
set search_path to ''
as $function$
  select jsonb_build_object(
    'c', jsonb_build_array(
      'id','cliente_id','cliente','telefone','email','tipo','categoria','origem','destino','data','hora',
      'data_volta','hora_volta','valor','repasse','status','canal','pax','motorista_id','veiculo_id','pago',
      'pago_em','meio','faturado','fatura','faturado_em','contrato','contrato_em','no_funil','criado_em',
      'linha','documento','cupom','quem','motorista_volta_id','esconder_valores','qtd_horas','conjunto'
    ),
    'r', coalesce((
      select jsonb_agg(jsonb_build_array(
        t.elem->'id', t.elem->'cliente_id', t.elem->'cliente', t.elem->'telefone', t.elem->'email',
        t.elem->'tipo', t.elem->'categoria', t.elem->'origem', t.elem->'destino', t.elem->'data', t.elem->'hora',
        t.elem->'data_volta', t.elem->'hora_volta', t.elem->'valor', t.elem->'repasse', t.elem->'status',
        t.elem->'canal', t.elem->'pax', t.elem->'motorista_id', t.elem->'veiculo_id', t.elem->'pago',
        t.elem->'pago_em', t.elem->'meio', t.elem->'faturado', t.elem->'fatura', t.elem->'faturado_em',
        t.elem->'contrato', t.elem->'contrato_em', t.elem->'no_funil', t.elem->'criado_em', t.elem->'linha',
        t.elem->'documento', t.elem->'cupom', t.elem->'quem', t.elem->'motorista_volta_id',
        t.elem->'esconder_valores', t.elem->'qtd_horas', t.elem->'conjunto'
      ) order by (t.elem->>'id')::int)
      from jsonb_array_elements(coalesce(p_doc, '[]'::jsonb)) as t(elem)
      where coalesce(p_de, '') = '' or coalesce(p_ate, '') = ''
         or coalesce(t.elem->>'data', '') between p_de and p_ate
         or coalesce(t.elem->>'data_volta', '') between p_de and p_ate
    ), '[]'::jsonb)
  );
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

create or replace function public.fln_ler_menu(
  p_segredo text,
  p_base smallint,
  p_modo text,
  p_de text,
  p_ate text,
  p_id integer
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
  if p_modo = 'casca' then
    select e.versao, (e.dados - 'transfers' - 'lancamentos') #- '{chat,mensagens}'
      into versao, doc
      from fln_privado.estado e
     where e.id = p_base;
    if versao is null then
      return jsonb_build_object('versao', 0, 'dados', null);
    end if;
    doc := doc || jsonb_build_object('_menu', 'casca');
    return jsonb_build_object('versao', versao, 'dados', doc);
  end if;

  if p_modo = 'caixa' then
    select e.versao, (e.dados - 'transfers' - 'lancamentos') #- '{chat,mensagens}', e.lancamentos_doc
      into versao, doc, lanc
      from fln_privado.estado e
     where e.id = p_base;
    if versao is null then
      return jsonb_build_object('versao', 0, 'dados', null);
    end if;
    doc := jsonb_set(doc, '{lancamentos}', coalesce(lanc, '[]'::jsonb), true);
    doc := doc || jsonb_build_object('_menu', 'caixa');
    return jsonb_build_object('versao', versao, 'dados', doc);
  end if;

  select e.versao, (e.dados - 'transfers' - 'lancamentos') #- '{chat,mensagens}', e.transfers_doc, e.lancamentos_doc
    into versao, doc, trips, lanc
    from fln_privado.estado e
   where e.id = p_base;
  if versao is null then
    return jsonb_build_object('versao', 0, 'dados', null);
  end if;
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

create or replace function public.fln_mapa(p_segredo text, p_base smallint default 1)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  saida jsonb;
begin
  perform fln_privado.conferir(p_segredo);
  select coalesce(jsonb_agg(jsonb_build_object(
    'id', t -> 'id',
    'status', coalesce(t ->> 'status', ''),
    'data', coalesce(t ->> 'data', ''),
    'hora', coalesce(t ->> 'hora', ''),
    'origem', coalesce(t ->> 'origem', ''),
    'destino', coalesce(t ->> 'destino', ''),
    'cliente', coalesce(t ->> 'cliente', '')
  )), '[]'::jsonb)
  into saida
  from fln_privado.estado e,
       jsonb_array_elements(coalesce(e.transfers_doc, e.dados -> 'transfers', '[]'::jsonb)) t
  where e.id = p_base;
  return coalesce(saida, '[]'::jsonb);
end
$function$;

revoke all on function public.fln_ler_menu(text, smallint, text, text, text, integer) from public;
grant execute on function public.fln_ler_menu(text, smallint, text, text, text, integer) to anon, authenticated, service_role;

create or replace view public.fln_resumo as
select
  e.id as base,
  e.versao,
  e.atualizado_em,
  e.atualizado_por,
  e.dados#>>'{settings,nome}' as empresa,
  e.dados#>>'{settings,usuario}' as admin_usuario,
  jsonb_array_length(coalesce(e.transfers_doc, e.dados->'transfers', '[]'::jsonb)) as transfers,
  jsonb_array_length(coalesce(e.dados->'clientes', '[]'::jsonb)) as clientes,
  jsonb_array_length(coalesce(e.dados->'motoristas', '[]'::jsonb)) as motoristas,
  jsonb_array_length(coalesce(e.dados->'veiculos', '[]'::jsonb)) as veiculos,
  jsonb_array_length(coalesce(e.lancamentos_doc, e.dados->'lancamentos', '[]'::jsonb)) as lancamentos
from fln_privado.estado e;

create or replace view public.fln_reservas as
select
  e.id as base,
  (t->>'id')::bigint as id,
  t->>'cliente' as cliente,
  t->>'status' as status,
  t->>'tipo' as tipo,
  t->>'categoria' as categoria,
  t->>'origem' as origem,
  t->>'destino' as destino,
  t->>'data' as data,
  t->>'hora' as hora,
  nullif(t->>'valor','')::numeric as valor,
  nullif(t->>'repasse','')::numeric as repasse,
  nullif(t->>'motorista_id','')::bigint as motorista_id,
  t->>'telefone' as telefone
from fln_privado.estado e
cross join lateral jsonb_array_elements(coalesce(e.transfers_doc, e.dados->'transfers', '[]'::jsonb)) t;

create or replace function public.fln_salvar(
  p_segredo text,
  p_versao bigint,
  p_dados jsonb,
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
  v bigint;
  k text;
  img text;
  imagem text;
  mexeu_imagem boolean := false;
  cache_chat jsonb;
  cache_aviso jsonb;
  transfers_col jsonb;
  lanc_col jsonb;
begin
  perform set_config('statement_timeout', '180s', true);
  perform fln_privado.conferir(p_segredo);
  if p_base not in (1, 2) then
    raise exception 'base inválida' using errcode = '22023';
  end if;
  if p_dados is null or jsonb_typeof(p_dados) <> 'object'
     or jsonb_typeof(p_dados -> 'motoristas') <> 'array'
     or jsonb_typeof(p_dados -> 'settings') <> 'object' then
    raise exception 'estado inválido' using errcode = '22023';
  end if;

  if p_versao <> 0 then
    select dados, transfers_doc, lancamentos_doc
      into atual, transfers_col, lanc_col
      from fln_privado.estado
     where id = p_base and versao = p_versao
     for update;
    if not found then
      return -1;
    end if;
    if transfers_col is not null then
      atual := jsonb_set(atual, '{transfers}', transfers_col, true);
    end if;
    if lanc_col is not null then
      atual := jsonb_set(atual, '{lancamentos}', lanc_col, true);
    end if;
    foreach k in array array['transfers', 'lancamentos', 'motoristas', 'clientes', 'veiculos'] loop
      if k in ('transfers', 'lancamentos') and not (p_dados ? k) then
        continue;
      end if;
      if fln_privado.contar(p_dados, k) < fln_privado.contar(atual, k) - 3 then
        raise exception 'Bloqueado: a gravação removeria vários registros de % de uma vez.', k using errcode = '22023';
      end if;
    end loop;
    if not (p_dados ? 'chat') then
      p_dados := jsonb_set(p_dados, array['chat'], coalesce(atual -> 'chat', '{}'::jsonb), true);
    elsif not (p_dados -> 'chat' ? 'mensagens') then
      p_dados := jsonb_set(p_dados, array['chat', 'mensagens'], coalesce(atual -> 'chat' -> 'mensagens', '{}'::jsonb), true);
    end if;
  end if;

  if p_dados ? 'transfers' then
    p_dados := fln_privado.sem_obs(p_base, p_dados);
  end if;

  foreach img in array array['logo', 'logo_lateral', 'favicon', 'assinatura'] loop
    if p_dados -> 'settings' ? img then
      imagem := coalesce(p_dados #>> array['settings', img], '');
      if imagem <> '' then
        mexeu_imagem := true;
        insert into fln_privado.midia (base, chave, valor)
        values (p_base, img, imagem)
        on conflict (base, chave) do update set valor = excluded.valor;
      elsif p_acao = 'remove_logo' then
        mexeu_imagem := true;
        delete from fln_privado.midia where base = p_base and chave = img;
      end if;
      p_dados := p_dados #- array['settings', img];
    end if;
    p_dados := jsonb_set(
      p_dados,
      array['settings', 'tem_' || img],
      to_jsonb(exists (
        select 1 from fln_privado.midia m
        where m.base = p_base and m.chave = img and m.valor <> ''
      )),
      true
    );
  end loop;

  if mexeu_imagem then
    p_dados := jsonb_set(
      p_dados,
      array['settings', 'marca_v'],
      to_jsonb(coalesce((atual #>> '{settings,marca_v}')::int, 0) + 1),
      true
    );
  end if;

  if p_dados ? 'transfers' and jsonb_typeof(p_dados -> 'transfers') = 'array' then
    if not (
      jsonb_array_length(p_dados -> 'transfers') = 0
      and transfers_col is not null
      and jsonb_array_length(transfers_col) > 3
    ) then
      transfers_col := p_dados -> 'transfers';
    end if;
  end if;
  if p_dados ? 'lancamentos' and jsonb_typeof(p_dados -> 'lancamentos') = 'array' then
    if not (
      jsonb_array_length(p_dados -> 'lancamentos') = 0
      and lanc_col is not null
      and jsonb_array_length(lanc_col) > 3
    ) then
      lanc_col := p_dados -> 'lancamentos';
    end if;
  end if;
  p_dados := p_dados - 'transfers' - 'lancamentos' - '_menu';

  cache_aviso := coalesce(p_dados -> 'avisos', '[]'::jsonb);
  cache_chat := fln_privado.chat_cache(p_dados);

  if p_versao = 0 then
    insert into fln_privado.estado (id, versao, dados, atualizado_por, aviso_cache, chat_cache, transfers_doc, lancamentos_doc)
    values (p_base, 1, p_dados, left(coalesce(p_usuario, ''), 80), cache_aviso, cache_chat, coalesce(transfers_col, '[]'::jsonb), coalesce(lanc_col, '[]'::jsonb))
    on conflict (id) do nothing
    returning versao into v;
    if v is null then
      return -1;
    end if;
  else
    update fln_privado.estado
       set versao = versao + 1,
           dados = p_dados,
           transfers_doc = coalesce(transfers_col, transfers_doc),
           lancamentos_doc = coalesce(lanc_col, lancamentos_doc),
           aviso_cache = cache_aviso,
           chat_cache = cache_chat,
           atualizado_em = now(),
           atualizado_por = left(coalesce(p_usuario, ''), 80)
     where id = p_base
    returning versao into v;
  end if;

  insert into fln_privado.historico (base, versao, acao, usuario, dados)
  values (
    p_base, v, left(coalesce(p_acao, ''), 60), left(coalesce(p_usuario, ''), 80),
    jsonb_build_object(
      'transfers', coalesce(jsonb_array_length(transfers_col), 0),
      'lancamentos', coalesce(jsonb_array_length(lanc_col), 0)
    )
  );
  return v;
end
$function$;

update fln_privado.estado
   set dados = dados - 'transfers' - 'lancamentos'
 where dados ? 'transfers' or dados ? 'lancamentos';

notify pgrst, 'reload schema';

do $chk2$
declare
  qtd int;
  lanc int;
  notas int;
  ainda boolean;
begin
  select jsonb_array_length(transfers_doc), jsonb_array_length(lancamentos_doc),
         (dados ? 'transfers' or dados ? 'lancamentos')
    into qtd, lanc, ainda
    from fln_privado.estado where id = 1;
  select count(*) into notas from fln_privado.anotacao where base = 1;
  if ainda or qtd <> 2805 or lanc <> 2908 or notas <> 2799 then
    raise exception 'conferência final inválida';
  end if;
end
$chk2$;
