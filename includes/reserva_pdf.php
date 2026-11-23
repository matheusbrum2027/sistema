<?php
declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

/**
 * PDF da reserva no mesmo desenho da folha: faixa, cartões, tabela, assinatura e contrato.
 */
function reserva_pdf_folha(array $db, array $trip): string
{
    $doc = new ReservaPdfFolha($db, $trip);
    return $doc->bytes();
}

final class ReservaPdfFolha
{
    private const LARGURA = 595.28;
    private const ALTURA = 841.89;
    private const MARGEM = 28.0;
    private const BASE = 58.0;

    private const NAVY = [0.102, 0.137, 0.196];
    private const ORANGE = [0.949, 0.420, 0.102];
    private const INK = [0.110, 0.141, 0.188];
    private const MUTED = [0.420, 0.451, 0.502];
    private const PEACH = [1.000, 0.957, 0.925];
    private const GELO = [0.953, 0.961, 0.973];
    private const LINHA = [0.890, 0.906, 0.933];
    private const LOCAL = [0.773, 0.800, 0.839];
    private const BRANCO = [1, 1, 1];

    /** @var list<FolhaPdf> */
    private array $paginas = [];
    /** @var array<string, array{jpeg:string,w:int,h:int}> */
    private array $imagens = [];
    private FolhaPdf $pagina;
    private float $y = 0;
    private int $reservaId = 0;
    private string $kicker = 'Documento';
    private string $titulo = 'Reserva';
    private array $db;
    private array $trip;
    private array $settings;
    private string $rodape;

    public function __construct(array $db, array $trip)
    {
        $this->db = $db;
        $this->trip = function_exists('trip_com_obs') ? trip_com_obs($trip) : $trip;
        $this->settings = is_array($db['settings'] ?? null) ? $db['settings'] : [];
        $this->reservaId = (int) ($this->trip['id'] ?? 0);
        $this->rodape = reserva_rodape_texto($this->settings);
    }

    public function bytes(): string
    {
        $this->abrir('Documento', 'Reserva #' . $this->reservaId);
        $this->destaques();
        $this->secao('Dados da reserva');
        $this->tabela(reserva_linhas_confirmacao(reserva_linhas($this->db, $this->trip), $this->trip));
        $this->trajetos();
        $nota = trim((string) ($this->settings['comp_rodape'] ?? ''));
        if ($nota !== '') {
            $this->paragrafo($nota, 8.5, false, [0.247, 0.282, 0.337]);
        }
        $this->assinatura();

        if (!empty($this->settings['contrato_pdf'])) {
            $temContrato = false;
            foreach ([
                'contrato_modelo' => 'Contrato',
                'contrato_cancelamento' => 'Cancelamento',
                'contrato_clausulas' => 'Cláusulas',
            ] as $chave => $titulo) {
                $texto = trim((string) ($this->settings[$chave] ?? ''));
                if ($texto === '') {
                    continue;
                }
                $texto = apply_template($texto, $this->trip, $this->db);
                $this->abrir(
                    str_contains(mb_strtolower($titulo), 'contrato') ? 'Transfer › Contrato' : 'Documento',
                    'Reserva #' . $this->reservaId . ' · ' . $titulo
                );
                $this->contrato($titulo, $texto);
                $temContrato = true;
            }
            if ($temContrato) {
                $this->assinatura();
            }
        }

        return $this->montar();
    }

    private function abrir(string $kicker, string $titulo): void
    {
        $this->kicker = $kicker;
        $this->titulo = $titulo;
        $this->pagina = new FolhaPdf();
        $this->paginas[] = $this->pagina;
        $this->pagina->ret(0, 0, self::LARGURA, self::ALTURA, self::BRANCO);
        $this->faixa();
        $this->cabecalho();
        $this->empresa();
    }

    private function continuar(): void
    {
        $this->abrir($this->kicker, $this->titulo);
    }

    private function faixa(): void
    {
        $altura = 86.0;
        $topo = self::ALTURA;
        $this->pagina->ret(0, $topo - $altura, self::LARGURA, $altura, self::NAVY);
        $this->pagina->ret(0, $topo - $altura - 3, self::LARGURA, 3, self::ORANGE);

        $caixa = 58.0;
        $x = self::MARGEM;
        $y = $topo - 14 - $caixa;
        $this->pagina->ret($x, $y, $caixa, $caixa, self::BRANCO);
        $logo = $this->logo();
        if ($logo !== null) {
            $this->desenharImagem('ImLogo', $logo, $x + 4, $y + 4, $caixa - 8, $caixa - 8);
        } else {
            $marca = trim((string) ($this->settings['nome'] ?? '')) ?: 'FLN';
            $sigla = mb_strtoupper(mb_substr($marca, 0, 3));
            $this->pagina->ret($x + 6, $y + 6, $caixa - 12, $caixa - 12, self::ORANGE);
            $this->pagina->txt($x + 14, $y + 24, 12, $sigla, true, self::BRANCO);
        }

        $textoX = $x + $caixa + 14;
        $textoL = self::LARGURA - $textoX - self::MARGEM;
        $marca = mb_strtoupper(trim((string) ($this->settings['nome'] ?? '')) ?: 'TRANSFER FLN');
        $razao = trim((string) ($this->settings['razao'] ?? '')) ?: 'Fln Transfer Executivo e Turismo';
        $local = reserva_cidade_uf($this->settings);
        $this->pagina->txt($textoX, $topo - 28, 8, $this->umaLinha($marca, $textoL, 8, true), true, self::ORANGE);
        $linhasRazao = $this->quebrar($razao, $textoL, 13, true);
        $base = $topo - 46;
        foreach (array_slice($linhasRazao, 0, 2) as $i => $linha) {
            $this->pagina->txt($textoX, $base - ($i * 15), 13, $linha, true, self::BRANCO);
        }
        if ($local !== '') {
            $this->pagina->txt($textoX, $topo - 74, 9, $this->umaLinha($local, $textoL, 9, false), false, self::LOCAL);
        }
        $this->y = $topo - $altura - 16;
    }

    private function cabecalho(): void
    {
        $this->pagina->txt(self::MARGEM, $this->y - 10, 8, mb_strtoupper($this->kicker), true, self::ORANGE);
        $linhas = $this->quebrar($this->titulo, 360, 16, true);
        $this->pagina->txt(self::MARGEM, $this->y - 30, 16, $linhas[0] ?? $this->titulo, true, self::INK);
        $emitido = 'Emitido em ' . today()->format('d/m/Y');
        $largura = $this->medida($emitido, 9, false);
        $this->pagina->txt(self::LARGURA - self::MARGEM - $largura, $this->y - 12, 9, $emitido, false, self::MUTED);
        $this->y -= 46;
    }

    private function empresa(): void
    {
        $itens = [];
        foreach ([
            'CNPJ' => (string) ($this->settings['cnpj'] ?? ''),
            'Telefone' => (string) ($this->settings['telefone'] ?? ''),
            'WhatsApp' => (string) ($this->settings['whatsapp'] ?? ''),
            'E-mail' => (string) ($this->settings['email'] ?? ''),
            'Representante' => (string) ($this->settings['rep_legal'] ?? ''),
            'Endereço' => (string) ($this->settings['endereco'] ?? ''),
        ] as $rotulo => $valor) {
            $valor = trim($valor);
            if ($valor !== '') {
                $itens[] = [$rotulo, $valor];
            }
        }
        if ($itens === []) {
            return;
        }
        $colunas = 3;
        $largura = self::LARGURA - (self::MARGEM * 2);
        $colW = $largura / $colunas;
        $linhas = (int) ceil(count($itens) / $colunas);
        $altura = 14 + ($linhas * 28);
        $this->garantir($altura + 8);
        $baixo = $this->y - $altura;
        $this->pagina->ret(self::MARGEM, $baixo, $largura, $altura, self::GELO);
        $this->pagina->ret(self::MARGEM, $baixo, 4, $altura, self::ORANGE);
        foreach ($itens as $i => [$rotulo, $valor]) {
            $col = $i % $colunas;
            $lin = intdiv($i, $colunas);
            $x = self::MARGEM + 12 + ($col * $colW);
            $y = $this->y - 16 - ($lin * 28);
            $this->pagina->txt($x, $y, 7, mb_strtoupper($rotulo), true, self::ORANGE);
            $this->pagina->txt($x, $y - 12, 9, $this->umaLinha($valor, $colW - 16, 9, true), true, self::INK);
        }
        $this->y = $baixo - 12;
    }

    private function destaques(): void
    {
        $quando = br_date((string) ($this->trip['data'] ?? ''));
        $hora = trim((string) ($this->trip['hora'] ?? ''));
        if ($hora !== '') {
            $quando = trim($quando . ' · ' . $hora);
        }
        $motorista = (string) ($this->trip['quem'] ?? '') === 'parceiro'
            ? 'Parceiro'
            : nome_motorista($this->db, $this->trip['motorista_id'] ?? 0);
        $status = status_label((string) ($this->trip['status'] ?? ''));
        $gap = 8.0;
        $largura = self::LARGURA - (self::MARGEM * 2);
        $meia = ($largura - $gap) / 2;
        $this->cartao(self::MARGEM, $meia, 40, 'Cliente', (string) ($this->trip['cliente'] ?? ''));
        $this->cartao(self::MARGEM + $meia + $gap, $meia, 40, 'Data e hora', $quando);
        $this->y -= 48;

        $origem = (string) ($this->trip['origem'] ?? '');
        $destino = (string) ($this->trip['destino'] ?? '');
        $centro = self::MARGEM + ($largura / 2);
        $colRota = $centro - self::MARGEM - 36;
        $linhasOrigem = array_slice($this->quebrar($origem !== '' ? $origem : '—', $colRota, 10, true), 0, 3);
        $linhasDestino = array_slice($this->quebrar($destino !== '' ? $destino : '—', $colRota, 10, true), 0, 3);
        $alturaRota = 28 + (max(count($linhasOrigem), count($linhasDestino), 1) * 12);
        $this->garantir($alturaRota + 8);
        $baixo = $this->y - $alturaRota;
        $this->pagina->ret(self::MARGEM, $baixo, $largura, $alturaRota, self::BRANCO);
        $this->pagina->borda(self::MARGEM, $baixo, $largura, $alturaRota, self::LINHA);
        $destX = $centro + 28;
        $this->rotulo(self::MARGEM + 10, $this->y - 14, 'Embarque');
        $this->rotulo($destX, $this->y - 14, 'Desembarque');
        foreach ($linhasOrigem as $i => $linha) {
            $this->pagina->txt(self::MARGEM + 10, $this->y - 28 - ($i * 12), 10, $linha, true, self::INK);
        }
        foreach ($linhasDestino as $i => $linha) {
            $this->pagina->txt($destX, $this->y - 28 - ($i * 12), 10, $linha, true, self::INK);
        }
        $meio = $centro - 18;
        $tracoY = $baixo + ($alturaRota / 2);
        for ($i = 0; $i < 5; $i++) {
            $this->pagina->ret($meio + ($i * 7), $tracoY, 4, 1.6, self::ORANGE);
        }
        $this->pagina->ponto($meio, $tracoY + 0.8, self::ORANGE);
        $this->pagina->ponto($meio + 36, $tracoY + 0.8, self::ORANGE);
        $this->y = $baixo - 8;

        $this->cartao(self::MARGEM, $meia, 40, 'Motorista', $motorista !== '' ? $motorista : '—');
        $xStatus = self::MARGEM + $meia + $gap;
        $this->garantir(40);
        $baixoStatus = $this->y - 40;
        $this->pagina->ret($xStatus, $baixoStatus, $meia, 40, self::BRANCO);
        $this->pagina->borda($xStatus, $baixoStatus, $meia, 40, self::LINHA);
        $this->rotulo($xStatus + 10, $this->y - 14, 'Status');
        $pill = $this->medida($status, 8, true) + 16;
        $this->pagina->pilula($xStatus + 10, $baixoStatus + 8, max(54, $pill), 14, self::ORANGE);
        $this->pagina->txt($xStatus + 18, $baixoStatus + 11, 8, $status, true, self::BRANCO);
        $this->y = $baixoStatus - 14;
    }

    private function cartao(float $x, float $w, float $h, string $rotulo, string $valor): void
    {
        $baixo = $this->y - $h;
        $this->pagina->ret($x, $baixo, $w, $h, self::BRANCO);
        $this->pagina->borda($x, $baixo, $w, $h, self::LINHA);
        $this->rotulo($x + 10, $this->y - 14, $rotulo);
        $this->pagina->txt($x + 10, $baixo + 10, 11, $this->umaLinha($valor !== '' ? $valor : '—', $w - 20, 11, true), true, self::INK);
    }

    private function secao(string $titulo): void
    {
        $this->garantir(22);
        $this->pagina->txt(self::MARGEM, $this->y - 12, 9, mb_strtoupper($titulo), true, self::INK);
        $this->y -= 20;
    }

    private function tabela(array $linhas): void
    {
        $largura = self::LARGURA - (self::MARGEM * 2);
        $colRotulo = $largura * 0.34;
        $zebra = 0;
        foreach ($linhas as $rotulo => $valor) {
            $rotulo = (string) $rotulo;
            $valorLinhas = $this->quebrar((string) $valor, $largura - $colRotulo - 16, 9, true);
            $rotuloLinhas = $this->quebrar(mb_strtoupper($rotulo), $colRotulo - 16, 7.5, true);
            $total = max(count($valorLinhas), count($rotuloLinhas), 1);
            $inicio = 0;
            while ($inicio < $total) {
                $disponivel = $this->y - self::BASE - 10;
                $cabem = (int) floor($disponivel / 12);
                if ($cabem < 1) {
                    $this->continuar();
                    $this->secao('Dados da reserva');
                    continue;
                }
                $fatia = min($cabem, $total - $inicio);
                $altura = 8 + ($fatia * 12);
                $baixo = $this->y - $altura;
                if ($zebra % 2 === 0) {
                    $this->pagina->ret(self::MARGEM, $baixo, $largura, $altura, self::PEACH);
                }
                $this->pagina->linha(self::MARGEM, $baixo, self::MARGEM + $largura, $baixo, [0.937, 0.906, 0.875]);
                for ($i = 0; $i < $fatia; $i++) {
                    $yLinha = $this->y - 14 - ($i * 12);
                    if (isset($rotuloLinhas[$inicio + $i])) {
                        $this->pagina->txt(self::MARGEM + 8, $yLinha, 7.5, $rotuloLinhas[$inicio + $i], true, self::ORANGE);
                    }
                    if (isset($valorLinhas[$inicio + $i])) {
                        $this->pagina->txt(self::MARGEM + $colRotulo, $yLinha, 9, $valorLinhas[$inicio + $i], true, self::INK);
                    }
                }
                $this->y = $baixo;
                $inicio += $fatia;
            }
            $zebra++;
        }
        $this->y -= 12;
    }

    private function trajetos(): void
    {
        if ((string) ($this->trip['tipo'] ?? '') !== 'trajetos' || !is_array($this->trip['trajetos'] ?? null) || $this->trip['trajetos'] === []) {
            return;
        }
        $this->secao('Trajetos');
        $largura = self::LARGURA - (self::MARGEM * 2);
        foreach ($this->trip['trajetos'] as $trecho) {
            $titulo = trim((string) ($trecho['embarque'] ?? '') . ' → ' . (string) ($trecho['desembarque'] ?? ''));
            $sub = trim(br_date((string) ($trecho['data'] ?? '')) . ' ' . (string) ($trecho['hora'] ?? '') . ' · ' . (int) ($trecho['pax'] ?? 1) . ' passageiros');
            $linhas = $this->quebrar($titulo, $largura - 20, 10, true);
            $altura = 16 + (count($linhas) * 12) + 14;
            $this->garantir($altura + 6);
            $baixo = $this->y - $altura;
            $this->pagina->ret(self::MARGEM, $baixo, $largura, $altura, self::BRANCO);
            $this->pagina->borda(self::MARGEM, $baixo, $largura, $altura, self::LINHA);
            foreach ($linhas as $i => $linha) {
                $this->pagina->txt(self::MARGEM + 10, $this->y - 16 - ($i * 12), 10, $linha, true, self::INK);
            }
            $this->pagina->txt(self::MARGEM + 10, $baixo + 8, 8, $sub, false, self::MUTED);
            $this->y = $baixo - 8;
        }
    }

    private function contrato(string $titulo, string $texto): void
    {
        $tituloLow = mb_strtolower($titulo);
        if (str_contains($tituloLow, 'cancel')) {
            $this->secao($titulo);
            $this->paragrafo('POLÍTICA DE CANCELAMENTO', 8, true, self::MUTED);
            foreach (reserva_parse_cancelamento($texto) as $item) {
                $this->cancelamento($item);
            }
            return;
        }
        if (str_contains($tituloLow, 'cláusula') || str_contains($tituloLow, 'clausula')) {
            $this->secao($titulo);
            $this->paragrafo('CLÁUSULAS ADICIONAIS', 8, true, self::MUTED);
            foreach (reserva_parse_clausulas($texto) as $item) {
                $this->clausula($item);
            }
            return;
        }
        $this->secao($titulo);
        foreach (reserva_parse_secoes($texto) as $sec) {
            $this->secaoContrato($sec);
        }
    }

    private function secaoContrato(array $sec): void
    {
        $largura = self::LARGURA - (self::MARGEM * 2);
        $titulo = trim((string) ($sec['titulo'] ?? ''));
        $num = trim((string) ($sec['num'] ?? ''));
        if ($titulo !== '' || $num !== '') {
            $this->garantir(26);
            $baixo = $this->y - 24;
            $this->pagina->ret(self::MARGEM, $baixo, $largura, 24, self::NAVY);
            $x = self::MARGEM + 8;
            if ($num !== '') {
                $this->pagina->ret($x, $baixo + 4, 20, 16, self::ORANGE);
                $this->pagina->txt($x + 6, $baixo + 8, 9, $num, true, self::BRANCO);
                $x += 28;
            }
            $this->pagina->txt($x, $baixo + 8, 9, $this->umaLinha(mb_strtoupper($titulo), $largura - ($x - self::MARGEM) - 10, 9, true), true, self::BRANCO);
            $this->y = $baixo;
        }
        $corpo = [];
        foreach ($sec['itens'] ?? [] as $item) {
            $prefixo = trim((string) ($item['num'] ?? ''));
            $linha = trim((string) ($item['texto'] ?? ''));
            if ($prefixo !== '') {
                $linha = $prefixo . '. ' . $linha;
            }
            if ($linha !== '') {
                $corpo[] = $linha;
            }
        }
        if ($corpo === []) {
            $this->y -= 8;
            return;
        }
        $this->pagina->ret(self::MARGEM, $this->y - 4, $largura, 4, self::BRANCO);
        foreach ($corpo as $linha) {
            $this->paragrafo($linha, 9, false, self::INK, 4);
        }
        $this->y -= 8;
    }

    private function cancelamento(array $item): void
    {
        $cores = [
            'ok' => [0.122, 0.616, 0.333],
            'warn' => [0.831, 0.627, 0.090],
            'bad' => [0.902, 0.494, 0.133],
            'crit' => [0.753, 0.224, 0.169],
        ];
        $cor = $cores[$item['tone'] ?? ''] ?? self::ORANGE;
        $largura = self::LARGURA - (self::MARGEM * 2);
        $linhas = $this->quebrar((string) ($item['texto'] ?? ''), $largura - 92, 9, false);
        $altura = 12 + (max(count($linhas), 1) * 12);
        $this->garantir($altura + 6);
        $baixo = $this->y - $altura;
        $this->pagina->ret(self::MARGEM, $baixo, $largura, $altura, self::BRANCO);
        $this->pagina->borda(self::MARGEM, $baixo, $largura, $altura, self::LINHA);
        $this->pagina->ret(self::MARGEM, $baixo, 4, $altura, $cor);
        $badge = (string) ($item['badge'] ?? '');
        $pill = max(46, $this->medida($badge, 7.5, true) + 12);
        $this->pagina->pilula(self::MARGEM + 12, $baixo + ($altura / 2) - 7, $pill, 14, $cor);
        $this->pagina->txt(self::MARGEM + 18, $baixo + ($altura / 2) - 4, 7.5, $badge, true, self::BRANCO);
        foreach ($linhas as $i => $linha) {
            $this->pagina->txt(self::MARGEM + 12 + $pill + 8, $this->y - 16 - ($i * 12), 9, $linha, false, self::INK);
        }
        $this->y = $baixo - 6;
    }

    private function clausula(array $item): void
    {
        $largura = self::LARGURA - (self::MARGEM * 2);
        $num = trim((string) ($item['num'] ?? ''));
        $linhas = $this->quebrar((string) ($item['texto'] ?? ''), $largura - ($num !== '' ? 64 : 24), 9, false);
        $altura = 12 + (max(count($linhas), 1) * 12);
        $this->garantir($altura + 6);
        $baixo = $this->y - $altura;
        $this->pagina->ret(self::MARGEM, $baixo, $largura, $altura, self::BRANCO);
        $this->pagina->borda(self::MARGEM, $baixo, $largura, $altura, self::LINHA);
        $x = self::MARGEM + 10;
        if ($num !== '') {
            $this->pagina->ret($x, $baixo + ($altura / 2) - 8, 36, 16, self::ORANGE);
            $this->pagina->txt($x + 5, $baixo + ($altura / 2) - 4, 8, $num, true, self::BRANCO);
            $x += 46;
        }
        foreach ($linhas as $i => $linha) {
            $this->pagina->txt($x, $this->y - 16 - ($i * 12), 9, $linha, false, self::INK);
        }
        $this->y = $baixo - 6;
    }

    private function paragrafo(string $texto, float $tamanho, bool $negrito, array $cor, float $recuo = 0): void
    {
        $largura = self::LARGURA - (self::MARGEM * 2) - $recuo;
        foreach ($this->quebrar($texto, $largura, $tamanho, $negrito) as $linha) {
            $this->garantir(14);
            $this->pagina->txt(self::MARGEM + $recuo, $this->y - 11, $tamanho, $linha, $negrito, $cor);
            $this->y -= 13;
        }
    }

    private function assinatura(): void
    {
        $nome = trim((string) ($this->settings['assinatura_nome'] ?? ''));
        if ($nome === '') {
            $nome = trim((string) ($this->settings['rep_legal'] ?? ''));
        }
        $razao = trim((string) ($this->settings['razao'] ?? ''));
        $cliente = trim((string) ($this->trip['cliente'] ?? ''));
        $documento = trim((string) ($this->trip['documento'] ?? ''));
        $this->garantir(118);
        $largura = (self::LARGURA - (self::MARGEM * 2) - 28) / 2;
        $x2 = self::MARGEM + $largura + 28;
        $this->pagina->txt(self::MARGEM, $this->y - 12, 8, 'CONTRATANTE', true, self::MUTED);
        $this->pagina->txt($x2, $this->y - 12, 8, 'CONTRATADA', true, self::MUTED);
        $linhaY = $this->y - 62;
        $jpeg = reserva_assinatura_jpeg($this->settings);
        if ($jpeg !== null) {
            $this->imagens['ImAss'] = $jpeg;
            $escala = min(160 / max(1, $jpeg['w']), 36 / max(1, $jpeg['h']));
            $iw = $jpeg['w'] * $escala;
            $ih = $jpeg['h'] * $escala;
            $this->pagina->imagemNome('ImAss', $x2 + (($largura - $iw) / 2), $linhaY + 2, $iw, $ih);
        }
        $this->pagina->linha(self::MARGEM, $linhaY, self::MARGEM + $largura, $linhaY, self::INK);
        $this->pagina->linha($x2, $linhaY, $x2 + $largura, $linhaY, self::INK);
        $this->pagina->txt(self::MARGEM, $linhaY - 16, 10, $this->umaLinha($cliente, $largura, 10, true), true, self::INK);
        $this->pagina->txt($x2, $linhaY - 16, 10, $this->umaLinha($nome, $largura, 10, true), true, self::INK);
        $this->pagina->txt(self::MARGEM, $linhaY - 30, 8, $this->umaLinha($documento, $largura, 8, false), false, self::MUTED);
        $this->pagina->txt($x2, $linhaY - 30, 8, $this->umaLinha($razao, $largura, 8, false), false, self::MUTED);
        $this->y = $linhaY - 44;
    }

    private function garantir(float $altura): void
    {
        if ($this->y - $altura < self::BASE) {
            $this->continuar();
        }
    }

    private function rotulo(float $x, float $y, string $texto): void
    {
        $this->pagina->txt($x, $y, 7.5, mb_strtoupper($texto), true, self::ORANGE);
    }

    /** @param array{jpeg:string,w:int,h:int} $imagem */
    private function desenharImagem(string $nome, array $imagem, float $x, float $y, float $w, float $h): void
    {
        $this->imagens[$nome] = $imagem;
        $escala = min($w / max(1, $imagem['w']), $h / max(1, $imagem['h']));
        $iw = $imagem['w'] * $escala;
        $ih = $imagem['h'] * $escala;
        $this->pagina->imagemNome($nome, $x + (($w - $iw) / 2), $y + (($h - $ih) / 2), $iw, $ih);
    }

    /** @return array{jpeg:string,w:int,h:int}|null */
    private function logo(): ?array
    {
        $src = trim((string) ($this->settings['logo'] ?? ''));
        if ($src === '' && !empty($this->settings['tem_logo']) && function_exists('store_midia')) {
            try {
                $src = store_midia('logo');
            } catch (Throwable) {
                $src = '';
            }
        }
        if ($src === '') {
            $src = trim((string) ($this->settings['logo_lateral'] ?? ''));
        }
        if ($src === '' && !empty($this->settings['tem_logo_lateral']) && function_exists('store_midia')) {
            try {
                $src = store_midia('logo_lateral');
            } catch (Throwable) {
                $src = '';
            }
        }
        return reserva_pdf_jpeg($src);
    }

    private function montar(): string
    {
        $total = count($this->paginas);
        $partes = preg_split("/\n/", $this->rodape) ?: [];
        foreach ($this->paginas as $i => $pagina) {
            $pagina->linha(self::MARGEM, 46, self::LARGURA - self::MARGEM, 46, self::LINHA);
            $y = 32.0;
            foreach (array_slice($partes, 0, 2) as $parte) {
                $parte = trim($parte);
                if ($parte === '') {
                    continue;
                }
                $pagina->txt(self::MARGEM, $y, 7.5, $this->umaLinha($parte, 390, 7.5, false), false, self::MUTED);
                $y -= 11;
            }
            $pagina->txt(self::LARGURA - self::MARGEM - 110, 32, 9, 'Reserva #' . $this->reservaId, true, self::INK);
            $pagina->txt(self::LARGURA - self::MARGEM - 110, 20, 8, 'Página ' . ($i + 1) . ' de ' . $total, false, self::MUTED);
        }

        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $next = 5;
        $recursosImg = '';
        foreach ($this->imagens as $nome => $imagem) {
            $id = $next++;
            $fluxo = $imagem['jpeg'] . "\n";
            $objs[$id] = '<< /Type /XObject /Subtype /Image /Width ' . $imagem['w']
                . ' /Height ' . $imagem['h']
                . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($fluxo)
                . " >>\nstream\n" . $fluxo . 'endstream';
            $recursosImg .= '/' . $nome . ' ' . $id . ' 0 R ';
        }
        $kids = [];
        foreach ($this->paginas as $pagina) {
            $cid = $next++;
            $pid = $next++;
            $stream = $pagina->ops;
            $objs[$cid] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream';
            $xobj = $recursosImg !== '' ? ' /XObject << ' . $recursosImg . '>>' : '';
            $objs[$pid] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::LARGURA . ' ' . self::ALTURA . '] /Contents ' . $cid
                . ' 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >>' . $xobj . ' >> >>';
            $kids[] = $pid . ' 0 R';
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        ksort($objs);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $max = (int) max(array_keys($objs));
        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }

    private function umaLinha(string $texto, float $largura, float $tamanho, bool $negrito): string
    {
        $linhas = $this->quebrar($texto, $largura, $tamanho, $negrito);
        $linha = $linhas[0] ?? '';
        if (count($linhas) > 1 && $linha !== '') {
            $reticencias = rtrim($linha) . '...';
            while ($linha !== '' && $this->medida($reticencias, $tamanho, $negrito) > $largura) {
                $linha = mb_substr($linha, 0, -1);
                $reticencias = rtrim($linha) . '...';
            }
            return $reticencias;
        }
        return $linha;
    }

    /** @return list<string> */
    private function quebrar(string $texto, float $largura, float $tamanho, bool $negrito): array
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $saida = [];
        foreach (explode("\n", $texto) as $paragrafo) {
            $paragrafo = trim(preg_replace('/[ \t]+/u', ' ', $paragrafo) ?? '');
            if ($paragrafo === '') {
                $saida[] = '';
                continue;
            }
            $atual = '';
            foreach (preg_split('/\s+/u', $paragrafo) ?: [] as $palavra) {
                while ($palavra !== '' && $this->medida($palavra, $tamanho, $negrito) > $largura && mb_strlen($palavra) > 1) {
                    $corte = $this->cortar($palavra, $largura, $tamanho, $negrito);
                    if ($atual !== '') {
                        $saida[] = $atual;
                        $atual = '';
                    }
                    $saida[] = $corte;
                    $palavra = mb_substr($palavra, mb_strlen($corte));
                }
                $tentativa = $atual === '' ? $palavra : $atual . ' ' . $palavra;
                if ($atual !== '' && $this->medida($tentativa, $tamanho, $negrito) > $largura) {
                    $saida[] = $atual;
                    $atual = $palavra;
                } else {
                    $atual = $tentativa;
                }
            }
            if ($atual !== '') {
                $saida[] = $atual;
            }
        }
        return $saida === [] ? [''] : $saida;
    }

    private function cortar(string $palavra, float $largura, float $tamanho, bool $negrito): string
    {
        $n = mb_strlen($palavra);
        $fim = 1;
        for ($i = 1; $i <= $n; $i++) {
            if ($this->medida(mb_substr($palavra, 0, $i), $tamanho, $negrito) > $largura) {
                break;
            }
            $fim = $i;
        }
        return mb_substr($palavra, 0, $fim);
    }

    private function medida(string $texto, float $tamanho, bool $negrito): float
    {
        $win = pdf_simples_latin1($texto);
        $n = strlen($win);
        $unidades = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $unidades += $this->unidade($win[$i], $negrito);
        }
        return $unidades * $tamanho;
    }

    private function unidade(string $ch, bool $negrito): float
    {
        if ($ch === ' ') {
            return 0.28;
        }
        if (str_contains("ijlI.,:;'`|!", $ch)) {
            return $negrito ? 0.32 : 0.28;
        }
        if (str_contains('mwMW@%', $ch)) {
            return $negrito ? 0.86 : 0.78;
        }
        if (ctype_upper($ch)) {
            return $negrito ? 0.70 : 0.64;
        }
        return $negrito ? 0.56 : 0.50;
    }
}

/** @return array{jpeg:string,w:int,h:int}|null */
function reserva_pdf_jpeg(string $src): ?array
{
    if (!preg_match('#^data:image/[a-z0-9.+-]+;base64,([a-z0-9+/=\r\n]+)$#i', $src, $m)) {
        return null;
    }
    $bin = base64_decode($m[1], true);
    if (!is_string($bin) || $bin === '' || !function_exists('imagecreatefromstring')) {
        return null;
    }
    $im = @imagecreatefromstring($bin);
    if (!$im) {
        return null;
    }
    $w = imagesx($im);
    $h = imagesy($im);
    if ($w < 2 || $h < 2) {
        imagedestroy($im);
        return null;
    }
    $flat = imagecreatetruecolor($w, $h);
    if (!$flat) {
        imagedestroy($im);
        return null;
    }
    $branco = imagecolorallocate($flat, 255, 255, 255);
    imagefill($flat, 0, 0, $branco);
    imagealphablending($flat, true);
    imagecopy($flat, $im, 0, 0, 0, 0, $w, $h);
    ob_start();
    imagejpeg($flat, null, 85);
    $jpeg = ob_get_clean();
    imagedestroy($im);
    imagedestroy($flat);
    if (!is_string($jpeg) || strlen($jpeg) < 32) {
        return null;
    }
    return ['jpeg' => $jpeg, 'w' => $w, 'h' => $h];
}
