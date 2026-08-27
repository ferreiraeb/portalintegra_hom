<?php
namespace Support;

/**
 * Gerador PDF minimalista (texto, sem dependências externas).
 */
class SimplePdf
{
    private const PAGE_W = 595;
    private const PAGE_H = 842;
    private const MARGIN = 50;
    private const LINE_H = 14;

    /** @var array<int, array{size: float, text: string, bold: bool}> */
    private array $lines = [];

    public function title(string $text): void
    {
        $this->lines[] = ['size' => 16, 'text' => $text, 'bold' => true];
        $this->blank();
    }

    public function section(string $text): void
    {
        $this->blank();
        $this->lines[] = ['size' => 13, 'text' => $text, 'bold' => true];
    }

    public function line(string $text): void
    {
        $this->lines[] = ['size' => 12, 'text' => $text, 'bold' => false];
    }

    public function blank(): void
    {
        $this->lines[] = ['size' => 12, 'text' => '', 'bold' => false];
    }

    public function render(): string
    {
        $pages   = $this->buildPages();
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(
            static fn(int $i) => (2 + $i) . ' 0 R',
            range(1, count($pages))
        )) . '] /Count ' . count($pages) . ' >>';

        $fontRegular = (2 + count($pages) + 1) . ' 0 R';
        $fontBold    = (2 + count($pages) + 2) . ' 0 R';
        $contentStart = 2 + count($pages) + 3;

        foreach ($pages as $idx => $stream) {
            $pageObj    = 2 + $idx + 1;
            $contentObj = $contentStart + $idx;
            $objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . self::PAGE_W . ' ' . self::PAGE_H . '] /Contents ' . $contentObj . ' 0 R /Resources '
                . '<< /Font << /F1 ' . $fontRegular . ' /F2 ' . $fontBold . ' >> >> >>';
            $objects[$contentObj] = '<< /Length ' . strlen($stream) . " >>\nstream\n"
                . $stream . "\nendstream";
        }

        $objects[(int)$fontRegular] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[(int)$fontBold]    = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        ksort($objects, SORT_NUMERIC);
        $pdf  = "%PDF-1.4\n";
        $xref = [];
        $pos  = strlen($pdf);

        foreach ($objects as $num => $body) {
            $xref[$num] = $pos;
            $chunk = "{$num} 0 obj\n{$body}\nendobj\n";
            $pdf  .= $chunk;
            $pos   = strlen($pdf);
        }

        $xrefPos = strlen($pdf);
        $maxObj  = max(array_keys($objects));
        $pdf    .= "xref\n0 " . ($maxObj + 1) . "\n";
        $pdf    .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObj; $i++) {
            $pdf .= isset($xref[$i])
                ? sprintf("%010d 00000 n \n", $xref[$i])
                : "0000000000 00000 f \n";
        }
        $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }

    /** @return string[] */
    private function buildPages(): array
    {
        $pages      = [];
        $stream     = '';
        $y          = self::PAGE_H - self::MARGIN;
        $usableTop  = self::PAGE_H - self::MARGIN;
        $usableBot  = self::MARGIN;

        $flushPage = static function () use (&$pages, &$stream): void {
            if ($stream !== '') {
                $pages[] = $stream;
                $stream  = '';
            }
        };

        foreach ($this->lines as $line) {
            $size = $line['size'];
            $need = $size + 4;
            if ($y - $need < $usableBot) {
                $flushPage();
                $y = $usableTop;
            }
            if ($stream === '') {
                $y = $usableTop;
            }

            if ($line['text'] !== '') {
                $font = $line['bold'] ? '/F2' : '/F1';
                $text = self::textHex($line['text']);
                $stream .= "BT\n{$font} {$size} Tf\n"
                    . self::MARGIN . ' ' . round($y, 2) . " Td\n"
                    . "<{$text}> Tj\nET\n";
            }
            $y -= max(self::LINE_H, $size + 2);
        }

        $flushPage();
        if ($pages === []) {
            $pages[] = '';
        }

        return $pages;
    }

    private static function textHex(string $text): string
    {
        return strtoupper(bin2hex(self::toLatin1($text)));
    }

    private static function toLatin1(string $text): string
    {
        if (function_exists('mb_convert_encoding')) {
            $out = @mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
            if ($out !== false) {
                return $out;
            }
        }

        $out = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $text);
        return ($out !== false) ? $out : $text;
    }
}
