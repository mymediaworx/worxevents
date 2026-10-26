<?php

declare(strict_types=1);

namespace App\Domain\Tickets;

/**
 * One-page ticket PDF. The QR is drawn from the module matrix, so GD is not required.
 */
final class TicketPdf
{
    /**
     * @param  list<list<bool>>  $modules
     */
    public static function render(string $title, string $attendee, string $when, string $reference, string $token, array $modules): string
    {
        $size = count($modules);
        $drawing = ['0 0 0 rg'];

        if ($size > 0) {
            $side = 216;
            $module = $side / $size;

            foreach ($modules as $y => $row) {
                foreach ($row as $x => $dark) {
                    if ($dark !== true) {
                        continue;
                    }

                    $px = 72 + ($x * $module);
                    $py = 300 + (($size - 1 - $y) * $module);
                    $drawing[] = sprintf('%.2F %.2F %.2F %.2F re f', $px, $py, $module, $module);
                }
            }
        }

        $text = implode("\n", [
            'BT /F1 18 Tf 72 740 Td '.self::literal($title).' Tj ET',
            'BT /F1 12 Tf 72 710 Td '.self::literal($attendee).' Tj ET',
            'BT /F1 12 Tf 72 690 Td '.self::literal($when).' Tj ET',
            'BT /F1 12 Tf 72 670 Td '.self::literal('Reference '.$reference).' Tj ET',
            'BT /F1 9 Tf 72 250 Td '.self::literal($token).' Tj ET',
            'BT /F1 9 Tf 72 230 Td '.self::literal('Present this QR code at the door.').' Tj ET',
        ]);

        $stream = $text."\n".implode("\n", $drawing);
        $objects = [
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n",
            "2 0 obj << /Type /Pages /Count 1 /Kids [3 0 R] >> endobj\n",
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n",
            '4 0 obj << /Length '.strlen($stream)." >> stream\n".$stream."\nendstream\nendobj\n",
            "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private static function literal(string $text): string
    {
        $clean = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
        $clean = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $clean);

        return '('.$clean.')';
    }
}
