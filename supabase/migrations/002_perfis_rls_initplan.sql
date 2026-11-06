-- Evita reavaliar auth.uid() para cada linha da tabela perfis.
drop policy if exists perfis_ler_o_proprio on public.perfis;

create policy perfis_ler_o_proprio
  on public.perfis
  for select
  to authenticated
  using ((select auth.uid()) = user_id);
