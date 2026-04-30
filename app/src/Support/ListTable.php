<?php
namespace Support;

class ListTable
{
    private string $baseUrl;
    private array  $cols;
    private string $jsPrefix;

    private string $sort    = '';
    private string $dir     = 'asc';
    private int    $page    = 1;
    private int    $perPage = 25;
    private array  $filterValues = [];

    /**
     * @param string $baseUrl
     * @param array  $cols
     * @param string $jsPrefix
     */
    public function __construct(string $baseUrl, array $cols, string $jsPrefix = 'lt')
    {
        $this->baseUrl  = rtrim($baseUrl, '?&');
        $this->cols     = $cols;
        $this->jsPrefix = $jsPrefix;
    }

    public function readRequest(
        string $defaultSort    = '',
        string $defaultDir     = 'asc',
        int    $defaultPerPage = 25
    ): void {
        $sortable = [];
        foreach ($this->cols as $key => $col) {
            if (!empty($col['sortable'])) {
                $sortable[] = $col['sql_col'] ?? $key;
            }
        }

        $sort = $_GET['sort'] ?? $defaultSort;
        if ($sortable && !in_array($sort, $sortable, true)) {
            $sort = $defaultSort ?: ($sortable[0] ?? '');
        }
        $this->sort = $sort;

        $dir = strtolower($_GET['dir'] ?? $defaultDir);
        $this->dir = ($dir === 'desc') ? 'desc' : 'asc';

        $this->page = max(1, (int)($_GET['page'] ?? 1));

        $perPage = (int)($_GET['per_page'] ?? $defaultPerPage);
        $this->perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : $defaultPerPage;

        foreach ($this->cols as $key => $col) {
            if (empty($col['param']) || !isset($col['filter']) || $col['filter'] === null) continue;
            $param   = $col['param'];
            $default = $col['default'] ?? '';
            $this->filterValues[$param] = $_GET[$param] ?? $default;
        }
    }

    public function hasFilters(): bool
    {
        foreach ($this->cols as $key => $col) {
            if (empty($col['param']) || !isset($col['filter']) || $col['filter'] === null) continue;
            $param   = $col['param'];
            $default = $col['default'] ?? '';
            if (($this->filterValues[$param] ?? $default) !== $default) return true;
        }
        return false;
    }

    public function url(array $overrides = []): string
    {
        $params = [
            'sort'     => $this->sort,
            'dir'      => $this->dir,
            'per_page' => $this->perPage,
            'page'     => $this->page,
        ];
        foreach ($this->cols as $key => $col) {
            if (empty($col['param'])) continue;
            $param          = $col['param'];
            $params[$param] = $this->filterValues[$param] ?? ($col['default'] ?? '');
        }
        $params = array_merge($params, $overrides);
        $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
        return $this->baseUrl . (count($params) ? '?' . http_build_query($params) : '');
    }

    public function sortLink(string $colKey): string
    {
        $col = $this->cols[$colKey] ?? null;
        if (!$col) return '';

        $label = $col['label'] ?? $colKey;
        if (empty($col['sortable'])) {
            return htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        }

        $sqlCol = $col['sql_col'] ?? $colKey;
        $newDir = ($this->sort === $sqlCol && $this->dir === 'asc') ? 'desc' : 'asc';
        $icon   = ($this->sort === $sqlCol)
                    ? ($this->dir === 'asc' ? ' &uarr;' : ' &darr;')
                    : '';
        $url = $this->url(['sort' => $sqlCol, 'dir' => $newDir, 'page' => 1]);
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
             . '" class="text-primary font-weight-semibold text-nowrap">'
             . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . $icon . '</a>';
    }

    public function filterInput(string $colKey): string
    {
        $col = $this->cols[$colKey] ?? null;
        if (!$col || empty($col['filter'])) return '';

        $param = $col['param'] ?? $colKey;
        $val   = (string)($this->filterValues[$param] ?? ($col['default'] ?? ''));

        if ($col['filter'] === 'text') {
            return '<input type="text"'
                 . ' name="'  . htmlspecialchars($param, ENT_QUOTES, 'UTF-8') . '"'
                 . ' class="form-control form-control-sm"'
                 . ' placeholder="Filtrar..."'
                 . ' value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '">';
        }

        if ($col['filter'] === 'select') {
            $html = '<select'
                  . ' name="' . htmlspecialchars($param, ENT_QUOTES, 'UTF-8') . '"'
                  . ' class="form-control form-control-sm">';
            foreach ($col['options'] ?? [] as $optVal => $optLabel) {
                $sel   = ($val === (string)$optVal) ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars((string)$optVal, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
                       . htmlspecialchars((string)$optLabel, ENT_QUOTES, 'UTF-8') . '</option>';
            }
            $html .= '</select>';
            return $html;
        }

        return '';
    }

    public function cols(): array
    {
        return $this->cols;
    }

    public function buildWhere(array $extra = []): array
    {
        $clauses = ['1=1'];
        $binds   = [];

        foreach ($this->cols as $key => $col) {
            if (empty($col['param']) || !isset($col['filter']) || $col['filter'] === null) continue;
            $param = $col['param'];
            $val   = $this->filterValues[$param] ?? '';
            if ($val === '') continue;

            $sqlCol = $col['sql_col'] ?? $key;

            if ($col['filter'] === 'text') {
                $clauses[]        = "$sqlCol LIKE :$param";
                $binds[":$param"] = '%' . $val . '%';
            } elseif ($col['filter'] === 'select') {
                $clauses[]        = "$sqlCol = :$param";
                $binds[":$param"] = $val;
            }
        }

        foreach ($extra as $clause => $extraBinds) {
            $clauses[] = $clause;
            foreach ((array)$extraBinds as $k => $v) $binds[$k] = $v;
        }

        return ['sql' => 'WHERE ' . implode(' AND ', $clauses), 'binds' => $binds];
    }

    public function paginationFooter(
        int    $total,
        int    $from,
        int    $to,
        string $noun     = 'registros',
        bool   $filtered = false
    ): string {
        $lastPage = max(1, (int)ceil($total / $this->perPage));
        $jumpFn   = $this->jsPrefix . 'JumpPage';
        $jumpId   = $this->jsPrefix . 'PageJump';

        // Per-page select
        $ppOpts = '';
        foreach ([10, 25, 50, 100] as $pp) {
            $sel     = ($this->perPage === $pp) ? ' selected' : '';
            $ppOpts .= '<option value="' . $pp . '"' . $sel . '>' . $pp . '/pág.</option>';
        }
        $ppSelect = '<select name="per_page" class="form-control form-control-sm"'
                  . ' style="width:auto" onchange="this.form.submit()">'
                  . $ppOpts . '</select>';

        // Count text
        if ($total > 0) {
            $countText = 'Mostrando <strong>' . $from . '</strong>–<strong>' . $to . '</strong>'
                       . ' de <strong>' . number_format($total, 0, ',', '.') . '</strong> '
                       . htmlspecialchars($noun, ENT_QUOTES, 'UTF-8')
                       . ($filtered ? ' <span class="text-warning ml-1">(filtrado)</span>' : '');
        } else {
            $countText = 'Nenhum registro';
        }

        // Pagination nav + page-jump
        $nav = '';
        if ($lastPage > 1) {
            $pPrev = max(1, $this->page - 1);
            $pNext = min($lastPage, $this->page + 1);
            $d1    = ($this->page <= 1)          ? ' disabled' : '';
            $dN    = ($this->page >= $lastPage)  ? ' disabled' : '';

            $nav .= '<nav class="mr-2"><ul class="pagination pagination-sm mb-0">';
            $nav .= '<li class="page-item' . $d1 . '"><a class="page-link" href="'
                  . htmlspecialchars($this->url(['page' => 1]), ENT_QUOTES, 'UTF-8') . '">&laquo;</a></li>';
            $nav .= '<li class="page-item' . $d1 . '"><a class="page-link" href="'
                  . htmlspecialchars($this->url(['page' => $pPrev]), ENT_QUOTES, 'UTF-8') . '">&lsaquo;</a></li>';
            for ($p = max(1, $this->page - 2); $p <= min($lastPage, $this->page + 2); $p++) {
                $active = ($p === $this->page) ? ' active' : '';
                $nav   .= '<li class="page-item' . $active . '"><a class="page-link" href="'
                        . htmlspecialchars($this->url(['page' => $p]), ENT_QUOTES, 'UTF-8') . '">' . $p . '</a></li>';
            }
            $nav .= '<li class="page-item' . $dN . '"><a class="page-link" href="'
                  . htmlspecialchars($this->url(['page' => $pNext]), ENT_QUOTES, 'UTF-8') . '">&rsaquo;</a></li>';
            $nav .= '<li class="page-item' . $dN . '"><a class="page-link" href="'
                  . htmlspecialchars($this->url(['page' => $lastPage]), ENT_QUOTES, 'UTF-8') . '">&raquo;</a></li>';
            $nav .= '</ul></nav>';

            $nav .= '<div class="input-group input-group-sm" style="width:72px">'
                  . '<input type="text" inputmode="numeric" pattern="[0-9]*"'
                  . ' id="' . htmlspecialchars($jumpId, ENT_QUOTES, 'UTF-8') . '"'
                  . ' class="form-control text-center"'
                  . ' placeholder="' . $this->page . '"'
                  . ' onkeydown="if(event.key===\'Enter\'){' . $jumpFn . '();}">'
                  . '<div class="input-group-append">'
                  . '<button type="button" class="btn btn-outline-secondary"'
                  . ' onclick="' . $jumpFn . '()">Ir</button>'
                  . '</div></div>';
        }

        // Page-jump script
        $patternJson = json_encode($this->url(['page' => 'PAGENUM']));
        $script = '<script>(function(){'
                . 'var pattern=' . $patternJson . ';'
                . 'window.' . $jumpFn . '=function(){'
                . 'var input=document.getElementById(' . json_encode($jumpId) . ');'
                . 'var p=parseInt(input.value,10);'
                . 'var max=' . $lastPage . ';'
                . 'if(!isNaN(p)&&p>=1&&p<=max){'
                .   'window.location.href=pattern.replace(\'PAGENUM\',p);'
                . '}else{'
                .   'input.classList.add(\'is-invalid\');'
                .   'setTimeout(function(){input.classList.remove(\'is-invalid\');},1500);'
                . '}'
                . '};'
                . '})();</script>';

        $html  = '<div class="card-footer d-flex align-items-center flex-wrap" style="gap:.5rem">';

        // Left: count + per_page
        $html .= '<div class="d-flex align-items-center" style="flex:1;min-width:200px">';
        $html .= '<span class="small text-muted mr-3 text-nowrap">' . $countText . '</span>';
        $html .= $ppSelect;
        $html .= '</div>';

        // Center: nav + jump
        $html .= '<div class="d-flex align-items-center justify-content-center" style="flex:1;min-width:260px">';
        $html .= $nav;
        $html .= '</div>';

        // Right: spacer
        $html .= '<div style="flex:1;min-width:200px"></div>';

        $html .= '</div>'; // /.card-footer
        $html .= $script;

        return $html;
    }

    public function getSort(): string         { return $this->sort; }
    public function getDir(): string          { return $this->dir; }
    public function getPage(): int            { return $this->page; }
    public function getPerPage(): int         { return $this->perPage; }
    public function getFilterValues(): array  { return $this->filterValues; }
}


