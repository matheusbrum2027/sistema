-- Núcleo do painel: um documento JSONB por base (1 = produção, 2 = teste).
-- O PHP grava via RPC public.fln_ler / fln_salvar / fln_sessao_*.
-- Segredo: fln_privado.config.chave = 'segredo' (mesmo valor de FLN_SEGREDO no .env / Vercel).
-- Idempotente. Não apaga dados existentes.

create extension if not exists pgcrypto with schema extensions;

create schema if not exists fln_privado;
revoke all on schema fln_privado from public;
revoke all on schema fln_privado from anon, authenticated;

create table if not exists fln_privado.config (
  chave text primary key,
  valor text not null
);

create table if not exists fln_privado.estado (
  id smallint primary key default 1 check (id in (1, 2)),
  versao bigint not null,
  dados jsonb not null,
  atualizado_em timestamptz not null default now(),
  atualizado_por text not null default ''
);

create table if not exists fln_privado.historico (
  id bigserial primary key,
  base smallint not null default 1 check (base in (1, 2)),
  versao bigint not null,
  acao text not null,
  usuario text not null,
  dados jsonb not null,
  criado_em timestamptz not null default now()
);

create table if not exists fln_privado.sessoes (
  id text primary key,
  dados text not null,
  expira timestamptz not null
);

create index if not exists historico_base_criado_idx on fln_privado.historico (base, criado_em desc);
create index if not exists sessoes_expira_idx on fln_privado.sessoes (expira);

alter table fln_privado.config enable row level security;
alter table fln_privado.estado enable row level security;
alter table fln_privado.historico enable row level security;
alter table fln_privado.sessoes enable row level security;

create or replace function fln_privado.bloquear()
returns trigger
language plpgsql
set search_path to ''
as $function$
begin
  raise exception 'Operação bloqueada: % em %', tg_op, tg_table_name;
end
$function$;

create or replace function fln_privado.conferir(p_segredo text)
returns void
language plpgsql
security definer
set search_path to ''
as $function$
begin
  if p_segredo is null or length(p_segredo) < 32 or not exists (
    select 1 from fln_privado.config where chave = 'segredo' and valor = p_segredo
  ) then
    raise exception 'acesso negado' using errcode = '42501';
  end if;
end
$function$;

create or replace function fln_privado.contar(p jsonb, k text)
returns integer
language sql
immutable
set search_path to ''
as $function$
  select case when jsonb_typeof(p -> k) = 'array' then jsonb_array_length(p -> k) else 0 end
$function$;

-- Acesso direto a fln_privado.* fica bloqueado por falta de GRANT + RLS sem policies.
-- As RPCs SECURITY DEFINER são o único caminho do app.

create or replace function public.fln_ler(p_segredo text, p_base smallint default 1)
returns jsonb
language plpgsql
security definer
set search_path to ''
as $function$
declare
  r record;
begin
  perform fln_privado.conferir(p_segredo);
  select versao, dados into r from fln_privado.estado where id = p_base;
  if not found then
    return jsonb_build_object('versao', 0, 'dados', null);
  end if;
  return jsonb_build_object('versao', r.versao, 'dados', r.dados);
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
  if p_versao = 0 then
    insert into fln_privado.estado (id, versao, dados, atualizado_por)
    values (p_base, 1, p_dados, left(coalesce(p_usuario, ''), 80))
    on conflict (id) do nothing
    returning versao into v;
    if v is null then
      return -1;
    end if;
  else
    select dados into atual from fln_privado.estado where id = p_base and versao = p_versao for update;
    if not found then
      return -1;
    end if;
    foreach k in array array['transfers', 'lancamentos', 'motoristas', 'clientes', 'veiculos'] loop
      if fln_privado.contar(p_dados, k) < fln_privado.contar(atual, k) - 3 then
        raise exception 'Bloqueado: a gravação removeria vários registros de % de uma vez.', k using errcode = '22023';
      end if;
    end loop;
    update fln_privado.estado
       set versao = versao + 1, dados = p_dados, atualizado_em = now(),
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
    p_dados #- '{settings,logo}' #- '{settings,logo_lateral}' #- '{settings,favicon}' #- '{settings,assinatura}'
  );
  return v;
end
$function$;

create or replace function public.fln_sessao_ler(p_segredo text, p_id text)
returns text
language plpgsql
security definer
set search_path to ''
as $function$
declare
  d text;
begin
  perform fln_privado.conferir(p_segredo);
  select dados into d from fln_privado.sessoes where id = p_id and expira > now();
  return coalesce(d, '');
end
$function$;

create or replace function public.fln_sessao_gravar(p_segredo text, p_id text, p_dados text, p_segundos integer)
returns void
language plpgsql
security definer
set search_path to ''
as $function$
begin
  perform fln_privado.conferir(p_segredo);
  insert into fln_privado.sessoes (id, dados, expira)
  values (p_id, p_dados, now() + make_interval(secs => greatest(60, p_segundos)))
  on conflict (id) do update set dados = excluded.dados, expira = excluded.expira;
  delete from fln_privado.sessoes where expira < now() - interval '1 day';
end
$function$;

create or replace function public.fln_sessao_apagar(p_segredo text, p_id text)
returns void
language plpgsql
security definer
set search_path to ''
as $function$
begin
  perform fln_privado.conferir(p_segredo);
  delete from fln_privado.sessoes where id = p_id;
end
$function$;

revoke all on function public.fln_ler(text, smallint) from public;
revoke all on function public.fln_salvar(text, bigint, jsonb, text, text, smallint) from public;
revoke all on function public.fln_sessao_ler(text, text) from public;
revoke all on function public.fln_sessao_gravar(text, text, text, integer) from public;
revoke all on function public.fln_sessao_apagar(text, text) from public;

grant execute on function public.fln_ler(text, smallint) to anon, authenticated, service_role;
grant execute on function public.fln_salvar(text, bigint, jsonb, text, text, smallint) to anon, authenticated, service_role;
grant execute on function public.fln_sessao_ler(text, text) to anon, authenticated, service_role;
grant execute on function public.fln_sessao_gravar(text, text, text, integer) to anon, authenticated, service_role;
grant execute on function public.fln_sessao_apagar(text, text) to anon, authenticated, service_role;

-- Segredo: na Vercel/local use o mesmo FLN_SEGREDO.
-- insert into fln_privado.config (chave, valor) values ('segredo', 'SEU_SEGREDO_COM_32+_CHARS')
-- on conflict (chave) do update set valor = excluded.valor;
