<?php

namespace App\Models\Concerns;

trait ExportableTrait
{
    public function exportableURL()
    {
        return url('import/gdxp?classname=' . get_class($this) . '&id=' . $this->id);
    }

    abstract public function exportXML();

    abstract public function exportJSON();
}
