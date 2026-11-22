-- A gravação não pode segurar a linha por 80s: a tela fica carregando até estourar.
do $mig$
declare
  def text;
begin
  def := pg_get_functiondef('public.fln_salvar(text,bigint,jsonb,text,text,smallint)'::regprocedure);
  def := replace(def, 'SET statement_timeout TO ''80s''', 'SET statement_timeout TO ''20s''');
  def := replace(def, 'set_config(''statement_timeout'', ''80s'', true)', 'set_config(''lock_timeout'', ''3s'', true); perform set_config(''statement_timeout'', ''20s'', true)');
  if def not like '%20s%' then
    raise exception 'nao foi possivel encurtar o tempo da gravacao';
  end if;
  execute def;
end
$mig$;
