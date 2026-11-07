-- Políticas explícitas de negação + views de leitura no schema public
-- para o Table Editor mostrar a operação (resumo, motoristas, clientes, reservas).

drop policy if exists fln_privado_config_deny on fln_privado.config;
create policy fln_privado_config_deny on fln_privado.config for all to anon, authenticated using (false) with check (false);

drop policy if exists fln_privado_estado_deny on fln_privado.estado;
create policy fln_privado_estado_deny on fln_privado.estado for all to anon, authenticated using (false) with check (false);

drop policy if exists fln_privado_historico_deny on fln_privado.historico;
create policy fln_privado_historico_deny on fln_privado.historico for all to anon, authenticated using (false) with check (false);

drop policy if exists fln_privado_sessoes_deny on fln_privado.sessoes;
create policy fln_privado_sessoes_deny on fln_privado.sessoes for all to anon, authenticated using (false) with check (false);

create or replace view public.fln_resumo
with (security_invoker = true) as
select
  e.id as base,
  e.versao,
  e.atualizado_em,
  e.atualizado_por,
  e.dados#>>'{settings,nome}' as empresa,
  e.dados#>>'{settings,usuario}' as admin_usuario,
  jsonb_array_length(coalesce(e.dados->'transfers','[]'::jsonb)) as transfers,
  jsonb_array_length(coalesce(e.dados->'clientes','[]'::jsonb)) as clientes,
  jsonb_array_length(coalesce(e.dados->'motoristas','[]'::jsonb)) as motoristas,
  jsonb_array_length(coalesce(e.dados->'veiculos','[]'::jsonb)) as veiculos,
  jsonb_array_length(coalesce(e.dados->'lancamentos','[]'::jsonb)) as lancamentos
from fln_privado.estado e;

create or replace view public.fln_motoristas
with (security_invoker = true) as
select
  e.id as base,
  (m->>'id')::bigint as id,
  m->>'nome' as nome,
  m->>'email' as email,
  m->>'usuario' as usuario,
  m->>'telefone' as telefone,
  m->>'status' as status,
  m->>'cidade' as cidade,
  m->>'uf' as uf
from fln_privado.estado e
cross join lateral jsonb_array_elements(coalesce(e.dados->'motoristas','[]'::jsonb)) m;

create or replace view public.fln_clientes
with (security_invoker = true) as
select
  e.id as base,
  (c->>'id')::bigint as id,
  c->>'nome' as nome,
  c->>'email' as email,
  c->>'telefone' as telefone,
  c->>'documento' as documento,
  c->>'cidade' as cidade,
  c->>'uf' as uf
from fln_privado.estado e
cross join lateral jsonb_array_elements(coalesce(e.dados->'clientes','[]'::jsonb)) c;

create or replace view public.fln_reservas
with (security_invoker = true) as
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
cross join lateral jsonb_array_elements(coalesce(e.dados->'transfers','[]'::jsonb)) t;

revoke all on public.fln_resumo from anon, authenticated;
revoke all on public.fln_motoristas from anon, authenticated;
revoke all on public.fln_clientes from anon, authenticated;
revoke all on public.fln_reservas from anon, authenticated;
grant select on public.fln_resumo to postgres, service_role;
grant select on public.fln_motoristas to postgres, service_role;
grant select on public.fln_clientes to postgres, service_role;
grant select on public.fln_reservas to postgres, service_role;
