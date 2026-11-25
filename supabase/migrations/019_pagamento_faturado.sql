-- Reservas faturadas que ficaram sem meio e sem mapa de pagamento.
-- A edição abria com o valor e a forma em branco. Só estas linhas são completadas.

do $mig$
declare
  n int;
  v bigint;
begin
  select count(*) into n
    from fln_privado.estado e,
         lateral jsonb_array_elements(e.transfers_doc) t
   where e.id = 1
     and coalesce(t->>'meio', '') = ''
     and coalesce(t->>'faturado', '') in ('true', 't')
     and coalesce(t->>'valor', '') ~ '^[0-9]+(\.[0-9]+)?$'
     and (t->>'valor')::numeric > 0
     and (
       jsonb_typeof(t->'pagamentos') is distinct from 'object'
       or t->'pagamentos' = '{}'::jsonb
     );

  if n = 0 then
    return;
  end if;

  update fln_privado.estado e
     set versao = versao + 1,
         transfers_doc = (
           select coalesce(jsonb_agg(
             case
               when coalesce(elem->>'meio', '') = ''
                and coalesce(elem->>'faturado', '') in ('true', 't')
                and coalesce(elem->>'valor', '') ~ '^[0-9]+(\.[0-9]+)?$'
                and (elem->>'valor')::numeric > 0
                and (
                  jsonb_typeof(elem->'pagamentos') is distinct from 'object'
                  or elem->'pagamentos' = '{}'::jsonb
                )
               then jsonb_set(
                      jsonb_set(elem, '{meio}', '"faturado_empresa"'::jsonb, true),
                      '{pagamentos}',
                      jsonb_build_object('faturado_empresa', (elem->>'valor')::numeric),
                      true
                    )
               else elem
             end
             order by ord
           ), '[]'::jsonb)
           from jsonb_array_elements(e.transfers_doc) with ordinality as linha(elem, ord)
         )
   where e.id = 1
  returning versao into v;

  insert into fln_privado.historico (base, versao, acao, usuario, dados)
  values (1, v, 'pagamento_faturado', 'sistema', jsonb_build_object('completas', n));
end
$mig$;
