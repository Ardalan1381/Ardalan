<?php

namespace App\Support\Printing;

/**
 * A minimal, hand-rolled ESC/POS byte-stream builder — no external package.
 * Text-mode only (not bitmap): Farsi content is printed by switching the
 * printer's own character-code table (ESC t n) and transliterating to that
 * codepage before sending the bytes, shifting glyph shaping/joining to the
 * printer's firmware, which is standard real-world practice for Farsi
 * thermal receipts. This deliberately avoids needing an Arabic/Farsi text
 * *shaping* engine (HarfBuzz-class), which plain PHP/GD cannot do — GD can
 * only draw isolated, unjoined glyphs in the codepoint's own order, which
 * would render Persian text incorrectly regardless of font choice.
 */
class EscPosDocumentBuilder
{
    private string $bytes = '';

    private bool $farsiMode = false;

    public function init(): static
    {
        $this->bytes .= "\x1B\x40"; // ESC @

        return $this;
    }

    /**
     * Switches the printer to the given character-code table (commonly a
     * Farsi/Arabic codepage such as CP1256 on the hardware this table
     * number corresponds to for a specific printer model — see
     * Printer::farsi_codepage_table's own doc comment) and every
     * subsequent text() call transliterates to CP1256 until
     * selectDefaultCodepage() is called.
     */
    public function selectFarsiCodepage(int $table): static
    {
        $this->bytes .= "\x1B\x74".chr($table); // ESC t n
        $this->farsiMode = true;

        return $this;
    }

    public function selectDefaultCodepage(): static
    {
        $this->bytes .= "\x1B\x74\x00"; // ESC t 0 — USA/CP437 on virtually every ESC/POS printer
        $this->farsiMode = false;

        return $this;
    }

    public function alignLeft(): static
    {
        $this->bytes .= "\x1B\x61\x00";

        return $this;
    }

    public function alignCenter(): static
    {
        $this->bytes .= "\x1B\x61\x01";

        return $this;
    }

    public function boldOn(): static
    {
        $this->bytes .= "\x1B\x45\x01";

        return $this;
    }

    public function boldOff(): static
    {
        $this->bytes .= "\x1B\x45\x00";

        return $this;
    }

    public function doubleHeightOn(): static
    {
        $this->bytes .= "\x1D\x21\x01";

        return $this;
    }

    public function doubleHeightOff(): static
    {
        $this->bytes .= "\x1D\x21\x00";

        return $this;
    }

    /**
     * Appends one line of text, transliterated to CP1256 first if Farsi
     * mode is currently active (see selectFarsiCodepage()) — @iconv is
     * used because the target codepage may legitimately have no exact
     * mapping for every character (e.g. curly quotes), and TRANSLIT falls
     * back to a close approximation rather than failing the whole line.
     */
    public function text(string $text): static
    {
        $this->bytes .= $this->encode($text)."\n";

        return $this;
    }

    public function separator(int $width, string $char = '-'): static
    {
        $this->bytes .= str_repeat($char, $width)."\n";

        return $this;
    }

    /**
     * Left-aligned label / right-aligned value on one line, padded to
     * $width — the standard two-column receipt-line layout.
     */
    public function twoColumnLine(string $left, string $right, int $width): static
    {
        $leftLen = mb_strlen($left);
        $rightLen = mb_strlen($right);
        $padding = max(1, $width - $leftLen - $rightLen);

        $this->bytes .= $this->encode($left).str_repeat(' ', $padding).$this->encode($right)."\n";

        return $this;
    }

    public function feed(int $lines = 1): static
    {
        $this->bytes .= str_repeat("\n", $lines);

        return $this;
    }

    public function cut(): static
    {
        $this->bytes .= "\x1D\x56\x00"; // GS V 0 — full cut

        return $this;
    }

    public function toBytes(): string
    {
        return $this->bytes;
    }

    private function encode(string $text): string
    {
        if (! $this->farsiMode) {
            return $text;
        }

        $converted = @iconv('UTF-8', 'CP1256//TRANSLIT', $text);

        return $converted !== false ? $converted : $text;
    }
}
