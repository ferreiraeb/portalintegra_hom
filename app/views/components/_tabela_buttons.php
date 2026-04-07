<?php
/**
 * views/components/_tabela_buttons.php
 *
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  BOTÕES COMPARTILHADOS — Processar · Atualizar Valores · Exportar  ║
 * ║  Usado por: tabela_getman · tabela_boart · tabela_massey            ║
 * ║                                                                      ║
 * ║  Para alterar ícone, rótulo ou estilo dos botões em TODAS as views  ║
 * ║  de uma só vez, edite SOMENTE este arquivo.                         ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 *
 * API pública (funções globais, idempotentes):
 *
 *   tabela_btn_processar(array $override = []): string
 *     → <button type="submit"> com ícone/label da config.
 *
 *   tabela_btn_atualizar(string $id, int $total, bool $disabled, array $override): string
 *     → <button type="button"> com data-total=N. Para injetar data-op="..." (Massey),
 *       passe ['extra_attrs' => 'data-op="..."'] em $override.
 *
 *   tabela_btn_export(string $href, bool $disabled, array $override): string
 *     → <a> estilizado como botão; renderiza desabilitado quando $disabled = true.
 */

if (!function_exists('_tbtn_config')) {

    // ──────────────────────────────────────────────────────────────────────────
    // CONFIG CENTRAL — edite AQUI para refletir em todas as views
    // ──────────────────────────────────────────────────────────────────────────
    function _tbtn_config(): array
    {
        return [

            // ── Botão Processar ────────────────────────────────────────────────
            'processar' => [
                'icon'  => 'fas fa-sync',
                'label' => 'Processar',
                'class' => 'btn btn-success btn-sm',
                'title' => 'Processar a planilha e cruzar com DealerNet',
            ],

            // ── Botão Atualizar Valores ────────────────────────────────────────
            'atualizar' => [
                'icon'  => 'fas fa-database',
                'label' => 'Atualizar Valores',
                'class' => 'btn btn-primary btn-sm',
                'title' => 'Registrar auditoria e executar procedure no DealerNet',
            ],

            // ── Botão / Link Exportar CSV ──────────────────────────────────────
            'export' => [
                'icon'  => 'fas fa-file-export',
                'label' => 'Exportar CSV',
                'class' => 'btn btn-outline-secondary btn-sm',
                'title' => 'Exportar resultado como CSV',
            ],

        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // tabela_btn_processar — botão de submit do formulário
    // ──────────────────────────────────────────────────────────────────────────
    /**
     * Renderiza o botão "Processar" (type="submit").
     *
     * @param array $override  Sobrescreve qualquer chave de _tbtn_config()['processar'].
     *                         Use 'extra_attrs' para injetar atributos HTML extras (raw).
     */
    function tabela_btn_processar(array $override = []): string
    {
        $cfg   = array_merge(_tbtn_config()['processar'], $override);
        $extra = $cfg['extra_attrs'] ?? '';
        return sprintf(
            '<button type="submit" class="%s" title="%s" %s>'
            . '<i class="%s mr-1"></i> %s'
            . '</button>',
            e($cfg['class']),
            e($cfg['title']),
            $extra,
            e($cfg['icon']),
            e($cfg['label'])
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // tabela_btn_atualizar — botão de trigger para o fluxo AJAX de update
    // ──────────────────────────────────────────────────────────────────────────
    /**
     * Renderiza o botão "Atualizar Valores" (type="button").
     *
     * @param string $id        ID HTML para seleção no JS (ex: 'btn-update-getman')
     * @param int    $total     Total de linhas → escrito em data-total
     * @param bool   $disabled  Renderiza como desabilitado
     * @param array  $override  Sobrescreve config. Use 'extra_attrs' para data-op="..." (Massey)
     */
    function tabela_btn_atualizar(string $id, int $total = 0, bool $disabled = false, array $override = []): string
    {
        $cfg          = array_merge(_tbtn_config()['atualizar'], $override);
        $extra        = $cfg['extra_attrs'] ?? '';
        $disabledAttr = $disabled ? ' disabled' : '';
        return sprintf(
            '<button type="button" id="%s" class="%s" title="%s" data-total="%d"%s %s>'
            . '<i class="%s mr-1"></i> %s'
            . '</button>',
            e($id),
            e($cfg['class']),
            e($cfg['title']),
            $total,
            $disabledAttr,
            $extra,
            e($cfg['icon']),
            e($cfg['label'])
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // tabela_btn_export — link de exportação estilizado como botão
    // ──────────────────────────────────────────────────────────────────────────
    /**
     * Renderiza o link "Exportar CSV" (âncora estilizada como botão).
     *
     * @param string $href     URL completa da ação de exportação
     * @param bool   $disabled Renderiza como desabilitado (sem navegação)
     * @param array  $override Sobrescreve qualquer chave da config
     */
    function tabela_btn_export(string $href, bool $disabled = false, array $override = []): string
    {
        $cfg = array_merge(_tbtn_config()['export'], $override);
        if ($disabled) {
            return sprintf(
                '<a href="#" class="%s disabled mr-1" title="%s" aria-disabled="true" tabindex="-1">'
                . '<i class="%s mr-1"></i> %s'
                . '</a>',
                e($cfg['class']),
                e($cfg['title']),
                e($cfg['icon']),
                e($cfg['label'])
            );
        }
        return sprintf(
            '<a href="%s" class="%s mr-1" title="%s">'
            . '<i class="%s mr-1"></i> %s'
            . '</a>',
            e($href),
            e($cfg['class']),
            e($cfg['title']),
            e($cfg['icon']),
            e($cfg['label'])
        );
    }
}

