Ordem das migrações (mais antiga → mais nova)

001_acesso.sql                 public.perfis + RLS
002_perfis_rls_initplan.sql    policy select com (select auth.uid())
003_fln_estado_compartilhado.sql  schema fln_privado + RPCs fln_*
004_fln_rotina_cron.sql        pg_cron + pg_net (job HTTP do painel)

O painel NÃO usa uma tabela por entidade.
Tudo (reservas, clientes, motoristas, financeiro, config, WhatsApp, push, chat)
fica em fln_privado.estado.dados (jsonb), bases id=1 e id=2.

No Table Editor do Supabase, abra o schema "fln_privado" (não só "public").
public.perfis fica vazia: o login atual usa settings/motoristas dentro do JSON.
