<?php

namespace Osfermedrano\Backups\Backup;

/** Resultado inmutable de una ejecución de backup. */
final class ResultadoBackup
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $archivo = null, // ruta relativa en el disco (ej. mi-app/2026-11-02-03-00-00.zip)
        public readonly int $bytes = 0,
        public readonly ?string $motivo = null,
        public readonly ?bool $drive = null,     // null = Drive no conectado (no se intentó)
        public readonly ?string $driveMotivo = null,
    ) {}

    public static function ok(string $archivo, int $bytes): self
    {
        return new self(true, $archivo, $bytes);
    }

    public static function fallo(string $motivo): self
    {
        return new self(false, motivo: $motivo);
    }

    public function conDrive(bool $subido, ?string $motivo = null): self
    {
        return new self($this->ok, $this->archivo, $this->bytes, $this->motivo, $subido, $subido ? null : $motivo);
    }

    public function nombre(): ?string
    {
        return $this->archivo ? basename($this->archivo) : null;
    }

    public function toArray(): array
    {
        return [
            'ok' => $this->ok, 'archivo' => $this->archivo, 'bytes' => $this->bytes, 'motivo' => $this->motivo,
            'drive' => $this->drive, 'drive_motivo' => $this->driveMotivo,
        ];
    }

    public static function fromArray(array $d): self
    {
        $r = $d['ok'] ? self::ok($d['archivo'], (int) $d['bytes']) : self::fallo((string) $d['motivo']);

        return isset($d['drive']) ? $r->conDrive((bool) $d['drive'], $d['drive_motivo'] ?? null) : $r;
    }
}
