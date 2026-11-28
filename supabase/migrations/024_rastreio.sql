-- Links de rastreio. Os pontos ficam fora do documento das reservas.

create table if not exists fln_privado.rastreio (
  id bigint generated always as identity primary key,
  token text not null,
  reserva_id integer not null,
  papel text not null,
  nome text not null default '',
  telefone text not null default '',
  obs text not null default '',
  status text not null default 'aguardando',
  origem text not null default '',
  destino text not null default '',
  quando text not null default '',
  empresa text not null default '',
  criado_em timestamptz not null default pg_catalog.now(),
  iniciado_em timestamptz,
  encerrado_em timestamptz,
  comunicado_em timestamptz,
  km numeric(8,2) not null default 0,
  ultimo_lat double precision,
  ultimo_lng double precision,
  ultimo_em timestamptz,
  constraint rastreio_token_unico unique (token),
  constraint rastreio_papel check (papel in ('cliente', 'motorista')),
  constraint rastreio_status check (status in ('aguardando', 'ao_vivo', 'concluido', 'encerrado'))
);

create table if not exists fln_privado.rastreio_ponto (
  id bigint generated always as identity primary key,
  rastreio_id bigint not null references fln_privado.rastreio (id) on delete cascade,
  lat double precision not null,
  lng double precision not null,
  precisao double precision,
  criado_em timestamptz not null default pg_catalog.now()
);

create index if not exists rastreio_abertos on fln_privado.rastreio (status, reserva_id);
create index if not exists rastreio_ponto_viagem on fln_privado.rastreio_ponto (rastreio_id, id);

revoke all on table fln_privado.rastreio from public, anon, authenticated;
revoke all on table fln_privado.rastreio_ponto from public, anon, authenticated;

create or replace function fln_privado.rastreio_instante(p_data text, p_hora text)
returns timestamp
language plpgsql
stable
set search_path to ''
as $function$
declare
  dia text := left(coalesce(p_data, ''), 10);
  hora text := left(coalesce(p_hora, ''), 5);
  hoje text := to_char((pg_catalog.now() at time zone 'America/Sao_Paulo'), 'YYYY-MM-DD');
begin
  if dia !~ '^\d{4}-\d{2}-\d{2}$' then
    return null;
  end if;
  if hora ~ '^\d{2}:\d{2}$' then
    return (dia || ' ' || hora)::timestamp;
  end if;
  if dia > hoje then
    return (dia || ' 00:00')::timestamp;
  end if;
  return null;
end
$function$;

create or replace function fln_privado.rastreio_metros(
  lat1 double precision,
  lng1 double precision,
  lat2 double precision,
  lng2 double precision
)
returns numeric
language sql
immutable
set search_path to ''
as $function$
  select (6371000 * 2 * asin(least(1::double precision, sqrt(
    power(sin(radians(lat2 - lat1) / 2), 2)
    + cos(radians(lat1)) * cos(radians(lat2)) * power(sin(radians(lng2 - lng1) / 2), 2)
  ))))::numeric
$function$;

create or replace function public.fln_rastreio_painel(p_segredo text, p_base smallint)
returns jsonb
language plpgsql
stable
security definer
set search_path to ''
as $function$
declare
  agora timestamp := (pg_catalog.now() at time zone 'America/Sao_Paulo');
  futuras jsonb;
  ativos jsonb;
  historico jsonb;
begin
  perform fln_privado.conferir(p_segredo);
  with viagens as (
    select
      (elem->>'id')::int as id,
      left(coalesce(elem->>'cliente', ''), 80) as cliente,
      left(coalesce(elem->>'telefone', ''), 30) as telefone,
      left(coalesce(elem->>'origem', ''), 160) as origem,
      left(coalesce(elem->>'destino', ''), 160) as destino,
      left(coalesce(elem->>'tipo', ''), 20) as tipo,
      coalesce((elem->>'motorista_id')::int, 0) as motorista_id,
      coalesce((elem->>'motorista_volta_id')::int, 0) as motorista_volta_id,
      fln_privado.rastreio_instante(elem->>'data', elem->>'hora') as ida,
      case
        when coalesce(elem->>'tipo', '') = 'ida_volta'
          then fln_privado.rastreio_instante(elem->>'data_volta', elem->>'hora_volta')
        else null
      end as volta
    from fln_privado.estado e
    cross join lateral jsonb_array_elements(coalesce(e.transfers_doc, '[]'::jsonb)) elem
    where e.id = p_base
      and lower(coalesce(elem->>'status', '')) not in ('cancelada', 'concluida', 'concluída')
  ),
  futuras_src as (
    select *
    from viagens
    where (ida is not null and ida > agora)
       or (volta is not null and volta > agora)
    order by least(
      case when ida is not null and ida > agora then ida else '9999-12-31'::timestamp end,
      case when volta is not null and volta > agora then volta else '9999-12-31'::timestamp end
    ), id
    limit 2000
  )
  select coalesce(jsonb_agg(s.obj order by s.ord, s.id), '[]'::jsonb)
    into futuras
  from (
    select
      id,
      least(
        case when ida is not null and ida > agora then ida else '9999-12-31'::timestamp end,
        case when volta is not null and volta > agora then volta else '9999-12-31'::timestamp end
      ) as ord,
      jsonb_build_object(
        'id', id,
        'cliente', cliente,
        'telefone', telefone,
        'origem', origem,
        'destino', destino,
        'tipo', tipo,
        'motorista_id', motorista_id,
        'motorista_volta_id', motorista_volta_id,
        'ida', to_char(ida, 'YYYY-MM-DD HH24:MI'),
        'volta', to_char(volta, 'YYYY-MM-DD HH24:MI')
      ) as obj
    from futuras_src
  ) s;

  select coalesce(jsonb_agg(jsonb_build_object(
    'id', r.id,
    'token', r.token,
    'reserva_id', r.reserva_id,
    'papel', r.papel,
    'nome', r.nome,
    'telefone', r.telefone,
    'obs', r.obs,
    'status', r.status,
    'origem', r.origem,
    'destino', r.destino,
    'quando', r.quando,
    'criado_em', r.criado_em,
    'iniciado_em', r.iniciado_em,
    'encerrado_em', r.encerrado_em,
    'comunicado_em', r.comunicado_em,
    'km', r.km,
    'ultimo_em', r.ultimo_em
  ) order by r.criado_em desc), '[]'::jsonb)
    into ativos
  from fln_privado.rastreio r
  where r.status in ('aguardando', 'ao_vivo');

  select coalesce(jsonb_agg(x.obj), '[]'::jsonb)
    into historico
  from (
    select jsonb_build_object(
      'id', r.id,
      'reserva_id', r.reserva_id,
      'papel', r.papel,
      'nome', r.nome,
      'telefone', r.telefone,
      'obs', r.obs,
      'status', r.status,
      'origem', r.origem,
      'destino', r.destino,
      'quando', r.quando,
      'criado_em', r.criado_em,
      'iniciado_em', r.iniciado_em,
      'encerrado_em', r.encerrado_em,
      'km', r.km
    ) as obj
    from fln_privado.rastreio r
    where r.status in ('concluido', 'encerrado')
    order by coalesce(r.encerrado_em, r.criado_em) desc
    limit 40
  ) x;

  return jsonb_build_object('futuras', futuras, 'ativos', ativos, 'historico', historico);
end
$function$;

create or replace function public.fln_rastreio_criar(
  p_segredo text,
  p_base smallint,
  p_reserva integer,
  p_papel text,
  p_nome text,
  p_telefone text,
  p_obs text,
  p_empresa text
)
returns jsonb
language plpgsql
volatile
security definer
set search_path to ''
as $function$
declare
  agora timestamp := (pg_catalog.now() at time zone 'America/Sao_Paulo');
  elem jsonb;
  papel text := lower(trim(coalesce(p_papel, '')));
  ida timestamp;
  volta timestamp;
  futuro boolean;
  novo_id bigint;
  novo_token text;
  origem text;
  destino text;
  quando text;
begin
  perform fln_privado.conferir(p_segredo);
  if papel not in ('cliente', 'motorista') then
    raise exception 'papel_invalido' using errcode = 'P0001';
  end if;
  if coalesce(p_reserva, 0) <= 0 then
    raise exception 'reserva_obrigatoria' using errcode = 'P0001';
  end if;
  select t.elem
    into elem
  from fln_privado.estado e
  cross join lateral jsonb_array_elements(coalesce(e.transfers_doc, '[]'::jsonb)) t(elem)
  where e.id = p_base
    and (t.elem->>'id')::int = p_reserva
  limit 1;
  if elem is null then
    raise exception 'reserva_ausente' using errcode = 'P0001';
  end if;
  if lower(coalesce(elem->>'status', '')) in ('cancelada', 'concluida', 'concluída') then
    raise exception 'reserva_passada' using errcode = 'P0001';
  end if;
  ida := fln_privado.rastreio_instante(elem->>'data', elem->>'hora');
  if coalesce(elem->>'tipo', '') = 'ida_volta' then
    volta := fln_privado.rastreio_instante(elem->>'data_volta', elem->>'hora_volta');
  end if;
  futuro := (ida is not null and ida > agora) or (volta is not null and volta > agora);
  if not futuro then
    raise exception 'reserva_passada' using errcode = 'P0001';
  end if;
  if exists (
    select 1 from fln_privado.rastreio r
    where r.reserva_id = p_reserva
      and r.papel = papel
      and r.status in ('aguardando', 'ao_vivo')
  ) then
    raise exception 'link_aberto' using errcode = 'P0001';
  end if;
  novo_token := encode(extensions.gen_random_bytes(24), 'hex');
  origem := left(coalesce(elem->>'origem', ''), 160);
  destino := left(coalesce(elem->>'destino', ''), 160);
  quando := to_char(
    case
      when ida is not null and ida > agora then ida
      else volta
    end,
    'DD/MM/YYYY HH24:MI'
  );
  insert into fln_privado.rastreio (
    token, reserva_id, papel, nome, telefone, obs, origem, destino, quando, empresa
  ) values (
    novo_token,
    p_reserva,
    papel,
    left(trim(coalesce(p_nome, '')), 80),
    left(trim(coalesce(p_telefone, '')), 30),
    left(trim(coalesce(p_obs, '')), 400),
    origem,
    destino,
    coalesce(quando, ''),
    left(trim(coalesce(p_empresa, '')), 80)
  )
  returning id into novo_id;
  return jsonb_build_object('ok', true, 'id', novo_id, 'token', novo_token);
end
$function$;

create or replace function public.fln_rastreio_encerrar(p_segredo text, p_id bigint)
returns jsonb
language plpgsql
volatile
security definer
set search_path to ''
as $function$
declare
  n int;
begin
  perform fln_privado.conferir(p_segredo);
  update fln_privado.rastreio
     set status = 'encerrado',
         encerrado_em = pg_catalog.now()
   where id = coalesce(p_id, 0)
     and status in ('aguardando', 'ao_vivo');
  get diagnostics n = row_count;
  if n = 0 then
    raise exception 'link_fechado' using errcode = 'P0001';
  end if;
  return jsonb_build_object('ok', true);
end
$function$;

create or replace function public.fln_rastreio_comunicar(p_segredo text, p_id bigint)
returns jsonb
language plpgsql
stable
security definer
set search_path to ''
as $function$
declare
  linha fln_privado.rastreio%rowtype;
begin
  perform fln_privado.conferir(p_segredo);
  select * into linha
    from fln_privado.rastreio
   where id = coalesce(p_id, 0)
     and status in ('aguardando', 'ao_vivo');
  if not found then
    raise exception 'link_fechado' using errcode = 'P0001';
  end if;
  return jsonb_build_object(
    'ok', true,
    'telefone', linha.telefone,
    'token', linha.token,
    'reserva_id', linha.reserva_id,
    'quando', linha.quando,
    'origem', linha.origem,
    'destino', linha.destino,
    'papel', linha.papel,
    'nome', linha.nome
  );
end
$function$;

create or replace function public.fln_rastreio_pontos(p_segredo text, p_id bigint)
returns jsonb
language plpgsql
stable
security definer
set search_path to ''
as $function$
declare
  linha fln_privado.rastreio%rowtype;
  trilha jsonb;
begin
  perform fln_privado.conferir(p_segredo);
  select * into linha from fln_privado.rastreio where id = coalesce(p_id, 0);
  if not found then
    raise exception 'link_fechado' using errcode = 'P0001';
  end if;
  select coalesce(jsonb_agg(jsonb_build_array(p.lat, p.lng) order by p.id), '[]'::jsonb)
    into trilha
  from (
    select lat, lng, id
    from fln_privado.rastreio_ponto
    where rastreio_id = linha.id
    order by id desc
    limit 600
  ) p;
  return jsonb_build_object(
    'ok', true,
    'status', linha.status,
    'km', linha.km,
    'pontos', trilha
  );
end
$function$;

create or replace function public.fln_rastreio_publico(p_segredo text, p_token text)
returns jsonb
language plpgsql
stable
security definer
set search_path to ''
as $function$
declare
  linha fln_privado.rastreio%rowtype;
begin
  if p_token !~ '^[a-f0-9]{48}$' then
    return jsonb_build_object('ok', false);
  end if;
  select * into linha from fln_privado.rastreio where token = p_token;
  if not found then
    return jsonb_build_object('ok', false);
  end if;
  return jsonb_build_object(
    'ok', true,
    'status', linha.status,
    'papel', linha.papel,
    'nome', linha.nome,
    'origem', linha.origem,
    'destino', linha.destino,
    'quando', linha.quando,
    'empresa', linha.empresa,
    'km', linha.km
  );
end
$function$;

create or replace function public.fln_rastreio_ping(
  p_segredo text,
  p_token text,
  p_lat double precision,
  p_lng double precision,
  p_precisao double precision
)
returns jsonb
language plpgsql
volatile
security definer
set search_path to ''
as $function$
declare
  linha fln_privado.rastreio%rowtype;
  metros numeric := 0;
  qtd int;
begin
  if p_token !~ '^[a-f0-9]{48}$' then
    return jsonb_build_object('ok', false);
  end if;
  if p_lat is null or p_lng is null
     or p_lat < -90 or p_lat > 90
     or p_lng < -180 or p_lng > 180
     or (p_lat = 0 and p_lng = 0) then
    return jsonb_build_object('ok', false, 'erro', 'ponto');
  end if;
  select * into linha from fln_privado.rastreio where token = p_token for update;
  if not found then
    return jsonb_build_object('ok', false);
  end if;
  if linha.status not in ('aguardando', 'ao_vivo') then
    return jsonb_build_object('ok', false, 'erro', 'encerrado', 'status', linha.status);
  end if;
  if linha.ultimo_em is not null and linha.ultimo_em > pg_catalog.now() - interval '8 seconds' then
    return jsonb_build_object('ok', true, 'pulado', true, 'status', linha.status, 'km', linha.km);
  end if;
  select count(*) into qtd from fln_privado.rastreio_ponto where rastreio_id = linha.id;
  if qtd >= 1500 then
    return jsonb_build_object('ok', false, 'erro', 'limite', 'status', linha.status);
  end if;
  if linha.ultimo_lat is not null and linha.ultimo_lng is not null then
    metros := fln_privado.rastreio_metros(linha.ultimo_lat, linha.ultimo_lng, p_lat, p_lng);
    if metros < 12 or metros > 5000 or coalesce(p_precisao, 0) > 150 then
      metros := 0;
    end if;
  end if;
  insert into fln_privado.rastreio_ponto (rastreio_id, lat, lng, precisao)
  values (linha.id, p_lat, p_lng, p_precisao);
  update fln_privado.rastreio
     set status = 'ao_vivo',
         iniciado_em = coalesce(iniciado_em, pg_catalog.now()),
         ultimo_lat = p_lat,
         ultimo_lng = p_lng,
         ultimo_em = pg_catalog.now(),
         km = km + round((metros / 1000)::numeric, 2)
   where id = linha.id;
  return jsonb_build_object('ok', true, 'status', 'ao_vivo');
end
$function$;

create or replace function public.fln_rastreio_fim(p_segredo text, p_token text)
returns jsonb
language plpgsql
volatile
security definer
set search_path to ''
as $function$
declare
  n int;
begin
  if p_token !~ '^[a-f0-9]{48}$' then
    return jsonb_build_object('ok', false);
  end if;
  update fln_privado.rastreio
     set status = 'concluido',
         encerrado_em = pg_catalog.now()
   where token = p_token
     and status in ('aguardando', 'ao_vivo');
  get diagnostics n = row_count;
  if n = 0 then
    return jsonb_build_object('ok', false, 'erro', 'encerrado');
  end if;
  return jsonb_build_object('ok', true, 'status', 'concluido');
end
$function$;

revoke all on function public.fln_rastreio_painel(text, smallint) from public;
revoke all on function public.fln_rastreio_criar(text, smallint, integer, text, text, text, text, text) from public;
revoke all on function public.fln_rastreio_encerrar(text, bigint) from public;
create or replace function public.fln_rastreio_marcar(p_segredo text, p_id bigint)
returns jsonb
language plpgsql
volatile
security definer
set search_path to ''
as $function$
begin
  perform fln_privado.conferir(p_segredo);
  update fln_privado.rastreio
     set comunicado_em = pg_catalog.now()
   where id = coalesce(p_id, 0)
     and status in ('aguardando', 'ao_vivo');
  return jsonb_build_object('ok', true);
end
$function$;

revoke all on function public.fln_rastreio_comunicar(text, bigint) from public;
revoke all on function public.fln_rastreio_marcar(text, bigint) from public;
revoke all on function public.fln_rastreio_pontos(text, bigint) from public;
revoke all on function public.fln_rastreio_publico(text, text) from public;
revoke all on function public.fln_rastreio_ping(text, text, double precision, double precision, double precision) from public;
revoke all on function public.fln_rastreio_fim(text, text) from public;

grant execute on function public.fln_rastreio_painel(text, smallint) to anon, authenticated, service_role;
grant execute on function public.fln_rastreio_criar(text, smallint, integer, text, text, text, text, text) to anon, authenticated, service_role;
grant execute on function public.fln_rastreio_encerrar(text, bigint) to anon, authenticated, service_role;
grant execute on function public.fln_rastreio_comunicar(text, bigint) to anon, authenticated, service_role;
grant execute on function public.fln_rastreio_marcar(text, bigint) to anon, authenticated, service_role;
grant execute on function public.fln_rastreio_pontos(text, bigint) to anon, authenticated, service_role;
grant execute on function public.fln_rastreio_publico(text, text) to anon, authenticated, service_role;
grant execute on function public.fln_rastreio_ping(text, text, double precision, double precision, double precision) to anon, authenticated, service_role;
grant execute on function public.fln_rastreio_fim(text, text) to anon, authenticated, service_role;

notify pgrst, 'reload schema';
