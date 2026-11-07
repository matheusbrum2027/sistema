-- Agenda o cron do painel (WhatsApp pendente + Sheets) a cada 15 minutos.
-- Requer extensões pg_cron e pg_net (já usadas no projeto).
-- Substitua APP_URL e FLN_CRON pelos valores reais da Vercel antes de aplicar em projeto novo.
-- Neste projeto o job já existe; a migração só garante as extensões.

create extension if not exists pg_net with schema public;
create extension if not exists pg_cron with schema pg_catalog;

-- Exemplo (NÃO rode com placeholder em produção):
-- select cron.schedule(
--   'fln-rotina',
--   '*/15 * * * *',
--   $$select net.http_get(url := 'https://SEU-DOMINIO/index.php?cron=SEU_FLN_CRON', timeout_milliseconds := 55000)$$
-- );
