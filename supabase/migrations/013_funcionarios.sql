-- Funcionários do painel. A senha fica só nesta tabela, nunca no documento das reservas.
-- O usuário master não entra aqui e não pode ser apagado por esta função.

create table if not exists fln_privado.funcionario (
  id integer generated always as identity primary key,
  usuario text not null,
  nome text not null,
  senha text not null,
  ativo boolean not null default true,
  ver_financeiro boolean not null default false,
  criado_em timestamptz not null default now(),
  constraint funcionario_usuario_unico unique (usuario)
);

revoke all on table fln_privado.funcionario from public, anon, authenticated;

create or replace function public.fln_funcionario_buscar(p_segredo text, p_usuario text)
returns jsonb
language plpgsql
stable
security definer
set search_path to ''
as $function$
declare
  saida jsonb;
begin
  perform fln_privado.conferir(p_segredo);
  select jsonb_build_object(
    'id', f.id,
    'nome', f.nome,
    'usuario', f.usuario,
    'senha', f.senha,
    'ativo', f.ativo,
    'ver_financeiro', f.ver_financeiro
  )
    into saida
  from fln_privado.funcionario f
  where f.usuario = lower(trim(coalesce(p_usuario, '')));
  return saida;
end
$function$;

create or replace function public.fln_funcionarios(p_segredo text)
returns jsonb
language plpgsql
stable
security definer
set search_path to ''
as $function$
declare
  saida jsonb;
begin
  perform fln_privado.conferir(p_segredo);
  select coalesce(jsonb_agg(jsonb_build_object(
    'id', f.id,
    'nome', f.nome,
    'usuario', f.usuario,
    'ativo', f.ativo,
    'ver_financeiro', f.ver_financeiro
  ) order by f.nome, f.id), '[]'::jsonb)
    into saida
  from fln_privado.funcionario f;
  return saida;
end
$function$;

create or replace function public.fln_funcionario_salvar(
  p_segredo text,
  p_id integer,
  p_usuario text,
  p_nome text,
  p_senha text,
  p_ativo boolean,
  p_ver_financeiro boolean
)
returns integer
language plpgsql
volatile
security definer
set search_path to ''
as $function$
declare
  novo integer;
  login text := lower(trim(coalesce(p_usuario, '')));
begin
  perform fln_privado.conferir(p_segredo);
  if login !~ '^[a-z0-9][a-z0-9._-]{2,39}$' then
    raise exception 'usuario invalido' using errcode = '22023';
  end if;
  if length(trim(coalesce(p_nome, ''))) < 2 then
    raise exception 'nome invalido' using errcode = '22023';
  end if;
  if coalesce(p_id, 0) <= 0 then
    if coalesce(p_senha, '') = '' then
      raise exception 'senha obrigatoria' using errcode = '22023';
    end if;
    insert into fln_privado.funcionario (usuario, nome, senha, ativo, ver_financeiro)
    values (login, trim(p_nome), p_senha, coalesce(p_ativo, true), coalesce(p_ver_financeiro, false))
    returning id into novo;
    return novo;
  end if;
  update fln_privado.funcionario
     set usuario = login,
         nome = trim(p_nome),
         senha = case when coalesce(p_senha, '') <> '' then p_senha else senha end,
         ativo = coalesce(p_ativo, ativo),
         ver_financeiro = coalesce(p_ver_financeiro, false)
   where id = p_id
  returning id into novo;
  if novo is null then
    raise exception 'funcionario nao encontrado' using errcode = 'P0002';
  end if;
  return novo;
end
$function$;

create or replace function public.fln_funcionario_apagar(p_segredo text, p_id integer)
returns void
language plpgsql
volatile
security definer
set search_path to ''
as $function$
begin
  perform fln_privado.conferir(p_segredo);
  delete from fln_privado.funcionario where id = coalesce(p_id, 0);
end
$function$;

revoke all on function public.fln_funcionario_buscar(text, text) from public;
revoke all on function public.fln_funcionarios(text) from public;
revoke all on function public.fln_funcionario_salvar(text, integer, text, text, text, boolean, boolean) from public;
revoke all on function public.fln_funcionario_apagar(text, integer) from public;
grant execute on function public.fln_funcionario_buscar(text, text) to anon, authenticated, service_role;
grant execute on function public.fln_funcionarios(text) to anon, authenticated, service_role;
grant execute on function public.fln_funcionario_salvar(text, integer, text, text, text, boolean, boolean) to anon, authenticated, service_role;
grant execute on function public.fln_funcionario_apagar(text, integer) to anon, authenticated, service_role;

notify pgrst, 'reload schema';
