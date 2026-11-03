<?php

namespace Osfermedrano\Backups;

use Illuminate\Database\Eloquent\Model;

/** Estado propio del paquete (conexión de Drive). Tabla `backups_ajustes`. */
class Ajuste extends Model
{
    protected $table = 'backups_ajustes';

    protected $fillable = ['clave', 'valor'];

    public static function valor(string $clave): ?string
    {
        return static::where('clave', $clave)->value('valor');
    }

    public static function poner(string $clave, ?string $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }

    public static function quitar(array $claves): void
    {
        static::whereIn('clave', $claves)->delete();
    }
}
