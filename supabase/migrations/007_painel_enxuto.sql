-- Tira as observações do documento lido em toda página e guarda cada uma por reserva.
-- Aviso e chat passam a ler colunas pequenas, sem abrir o JSON inteiro.
-- O histórico deixa de guardar uma cópia completa a cada gravação.

create table if not exists fln_privado.anotacao (
  base smallint not null,
  transfer_id integer not null,
  texto text not null,
  primary key (base, transfer_id)
);

alter table fln_privado.anotacao enable row level security;
revoke all on fln_privado.anotacao from public, anon, authenticated;

insert into fln_privado.anotacao (base, transfer_id, texto)
select e.id, (t->>'id')::int, left(t->>'obs', 2000)
from fln_privado.estado e
cross join lateral jsonb_array_elements(e.dados -> 'transfers') t
where coalesce(t->>'obs', '') <> ''
on conflict (base, transfer_id) do update set texto = excluded.texto;

do $chk$
declare
  com int;
  grav int;
begin
  select count(*) into com
  from fln_privado.estado e
  cross join lateral jsonb_array_elements(e.dados -> 'transfers') t
  where coalesce(t->>'obs', '') <> '';
  select count(*) into grav from fln_privado.anotacao;
  if grav <> com then
    raise exception 'notas divergentes: json % tabela %', com, grav;
  end if;
end
$chk$;

create temp table fln_mig_antes as
select e.id as base,
       jsonb_array_length(e.dados -> 'transfers') as qtd,
       (select coalesce(sum((t->>'id')::bigint), 0)
          from jsonb_array_elements(e.dados -> 'transfers') t) as soma,
       (select min((t->>'id')::int) from jsonb_array_elements(e.dados -> 'transfers') t) as menor,
       (select max((t->>'id')::int) from jsonb_array_elements(e.dados -> 'transfers') t) as maior
from fln_privado.estado e;

update fln_privado.estado e
   set dados = jsonb_set(
     e.dados,
     '{transfers}',
     coalesce((
       select jsonb_agg(x.t - 'obs' order by x.ord)
       from jsonb_array_elements(e.dados -> 'transfers') with ordinality as x(t, ord)
     ), '[]'::jsonb),
     true
   );

do $chk2$
declare
  r record;
  qtd int;
  soma bigint;
  menor int;
  maior int;
  sobrou int;
begin
  for r in select * from fln_mig_antes loop
    select jsonb_array_length(dados -> 'transfers'),
           (select coalesce(sum((t->>'id')::bigint), 0) from jsonb_array_elements(dados -> 'transfers') t),
           (select min((t->>'id')::int) from jsonb_array_elements(dados -> 'transfers') t),
           (select max((t->>'id')::int) from jsonb_array_elements(dados -> 'transfers') t)
      into qtd, soma, menor, maior
      from fln_privado.estado
     where id = r.base;
    if qtd is distinct from r.qtd or soma is distinct from r.soma
       or menor is distinct from r.menor or maior is distinct from r.maior then
      raise exception 'reservas alteradas na base %', r.base;
    end if;
    select count(*) into sobrou
      from jsonb_array_elements((select dados -> 'transfers' from fln_privado.estado where id = r.base)) t
     where coalesce(t->>'obs', '') <> '';
    if sobrou <> 0 then
      raise exception 'obs ainda no documento da base %', r.base;
    end if;
  end loop;
end
$chk2$;

alter table fln_privado.estado
  add column if not exists aviso_cache jsonb,
  add column if not exists chat_cache jsonb;

create or replace function fln_privado.chat_cache(doc jsonb)
returns jsonb
language sql
immutable
set search_path to ''
as $function$
  select jsonb_build_object(
    'settings', jsonb_build_object(
      'wa_status', coalesce(doc #>> '{settings,wa_status}', ''),
      'wa_profile_name', coalesce(doc #>> '{settings,wa_profile_name}', ''),
      'uazapi_instance_name', coalesce(doc #>> '{settings,uazapi_instance_name}', ''),
      'uazapi_url', coalesce(doc #>> '{settings,uazapi_url}', ''),
      'uazapi_token', coalesce(doc #>> '{settings,uazapi_token}', ''),
      'chat_ignorar_grupos', coalesce(doc #> '{settings,chat_ignorar_grupos}', 'true'::jsonb)
    ),
    'chat', jsonb_build_object(
      'conversas', coalesce(doc #> '{chat,conversas}', '{}'::jsonb),
      'digitando', coalesce(doc #> '{chat,digitando}', '{}'::jsonb),
      'desde', coalesce(doc #> '{chat,desde}', '0'::jsonb),
      'secret', coalesce(doc #>> '{chat,secret}', '')
    )
  );
$function$;

update fln_privado.estado
   set aviso_cache = coalesce(dados -> 'avisos', '[]'::jsonb),
       chat_cache = fln_privado.chat_cache(dados);

create or replace function fln_privado.guardar_obs(p_base smallint, item jsonb)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  tid int;
  nota text;
  tocada boolean;
begin
  tid := coalesce((item ->> 'id')::int, 0);
  tocada := coalesce((item -> 'obs_tocada') = 'true'::jsonb, false);
  if tid > 0 and item ? 'obs' and (tocada or coalesce(item ->> 'obs', '') <> '') then
    nota := left(coalesce(item ->> 'obs', ''), 2000);
    if nota = '' then
      delete from fln_privado.anotacao where base = p_base and transfer_id = tid;
    else
      insert into fln_privado.anotacao (base, transfer_id, texto)
      values (p_base, tid, nota)
      on conflict (base, transfer_id) do update set texto = excluded.texto;
    end if;
  end if;
  return item - 'obs' - 'obs_tocada';
end
$function$;

create or replace function fln_privado.sem_obs(p_base smallint, p_dados jsonb)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  trips jsonb;
begin
  select coalesce(jsonb_agg(fln_privado.guardar_obs(p_base, x.t) order by x.ord), '[]'::jsonb)
    into trips
  from jsonb_array_elements(coalesce(p_dados -> 'transfers', '[]'::jsonb)) with ordinality as x(t, ord);
  return jsonb_set(p_dados, '{transfers}', trips, true);
end
$function$;

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
begin
  perform set_config('statement_timeout', '180s', true);
  perform fln_privado.conferir(p_segredo);
  if p_base not in (1, 2) then
    raise exception 'base inválida' using errcode = '22023';
  end if;
  if p_dados is null or jsonb_typeof(p_dados) <> 'object'
     or jsonb_typeof(p_dados -> 'transfers') <> 'array'
     or jsonb_typeof(p_dados -> 'motoristas') <> 'array'
     or jsonb_typeof(p_dados -> 'lancamentos') <> 'array'
     or jsonb_typeof(p_dados -> 'settings') <> 'object' then
    raise exception 'estado inválido' using errcode = '22023';
  end if;

  if p_versao <> 0 then
    select dados into atual from fln_privado.estado where id = p_base and versao = p_versao for update;
    if not found then
      return -1;
    end if;
    foreach k in array array['transfers', 'lancamentos', 'motoristas', 'clientes', 'veiculos'] loop
      if fln_privado.contar(p_dados, k) < fln_privado.contar(atual, k) - 3 then
        raise exception 'Bloqueado: a gravação removeria vários registros de % de uma vez.', k using errcode = '22023';
      end if;
    end loop;
    if not (p_dados ? 'chat') then
      p_dados := jsonb_set(p_dados, array['chat'], coalesce(atual -> 'chat', '{}'::jsonb), true);
    elsif not (p_dados -> 'chat' ? 'mensagens') then
      p_dados := jsonb_set(
        p_dados,
        array['chat', 'mensagens'],
        coalesce(atual -> 'chat' -> 'mensagens', '{}'::jsonb),
        true
      );
    end if;
  end if;

  p_dados := fln_privado.sem_obs(p_base, p_dados);

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

  cache_aviso := coalesce(p_dados -> 'avisos', '[]'::jsonb);
  cache_chat := fln_privado.chat_cache(p_dados);

  if p_versao = 0 then
    insert into fln_privado.estado (id, versao, dados, atualizado_por, aviso_cache, chat_cache)
    values (p_base, 1, p_dados, left(coalesce(p_usuario, ''), 80), cache_aviso, cache_chat)
    on conflict (id) do nothing
    returning versao into v;
    if v is null then
      return -1;
    end if;
  else
    update fln_privado.estado
       set versao = versao + 1,
           dados = p_dados,
           aviso_cache = cache_aviso,
           chat_cache = cache_chat,
           atualizado_em = now(),
           atualizado_por = left(coalesce(p_usuario, ''), 80)
     where id = p_base
    returning versao into v;
  end if;

  insert into fln_privado.historico (base, versao, acao, usuario, dados)
  values (
    p_base,
    v,
    left(coalesce(p_acao, ''), 60),
    left(coalesce(p_usuario, ''), 80),
    jsonb_build_object(
      'transfers', fln_privado.contar(p_dados, 'transfers'),
      'lancamentos', fln_privado.contar(p_dados, 'lancamentos')
    )
  );
  return v;
end
$function$;

create or replace function public.fln_avisos(
  p_segredo text,
  p_desde bigint,
  p_base smallint default 1
)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  doc jsonb;
  item jsonb;
  itens jsonb := '[]'::jsonb;
  ultimo bigint := greatest(coalesce(p_desde, 0), 0);
  idv bigint;
begin
  perform fln_privado.conferir(p_segredo);
  select aviso_cache into doc from fln_privado.estado where id = p_base;
  if doc is null then
    select coalesce(dados -> 'avisos', '[]'::jsonb) into doc
    from fln_privado.estado where id = p_base;
  end if;
  if doc is null or jsonb_typeof(doc) <> 'array' then
    return jsonb_build_object('ultimo', ultimo, 'itens', '[]'::jsonb);
  end if;
  for item in select value from jsonb_array_elements(doc) loop
    idv := coalesce((item ->> 'id')::bigint, 0);
    if idv > ultimo then
      ultimo := idv;
    end if;
    if idv > coalesce(p_desde, 0) then
      itens := itens || jsonb_build_array(item);
    end if;
  end loop;
  return jsonb_build_object('ultimo', ultimo, 'itens', itens);
end
$function$;

create or replace function public.fln_chat_resumo(p_segredo text, p_base smallint default 1)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  doc jsonb;
begin
  perform fln_privado.conferir(p_segredo);
  select chat_cache into doc from fln_privado.estado where id = p_base;
  if doc is null then
    select fln_privado.chat_cache(dados) into doc
    from fln_privado.estado where id = p_base;
  end if;
  return coalesce(doc, '{}'::jsonb);
end
$function$;

create or replace function public.fln_anotacao(
  p_segredo text,
  p_id integer,
  p_base smallint default 1
)
returns text
language plpgsql
security definer
set search_path to ''
as $function$
declare
  v text;
begin
  perform fln_privado.conferir(p_segredo);
  select texto into v
  from fln_privado.anotacao
  where base = p_base and transfer_id = p_id;
  return coalesce(v, '');
end
$function$;

revoke all on function public.fln_anotacao(text, integer, smallint) from public;
grant execute on function public.fln_anotacao(text, integer, smallint) to anon, authenticated, service_role;

alter table fln_privado.historico disable trigger user;
delete from fln_privado.historico;
alter table fln_privado.historico enable trigger user;
