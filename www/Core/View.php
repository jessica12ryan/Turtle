<?php

namespace App\Core;

class View
{
    private string $layout = '';
    private array $layoutData = [];
    private string $content = '';

    public function layout(string $layout, array $data = []): void
    {
        $this->layout = $layout;
        $this->layoutData = $data;
    }

    public function render(string $view, array $data = []): void
    {
        // Copy and unset the parameter before extracting: EXTR_SKIP would
        // otherwise refuse to create a view variable called "data", because
        // the parameter of that name already exists in this scope.
        $__viewData = $data;
        unset($data);
        extract($__viewData, EXTR_SKIP);

        ob_start();
        require base_path("www/Views/{$view}.php");
        $this->content = ob_get_clean();

        if ($this->layout) {
            $data = $this->layoutData;
            $data['content'] = $this->content;
            $__layoutData = $data;
            unset($data);
            extract($__layoutData, EXTR_SKIP);
            require base_path("www/Views/{$this->layout}.php");
        } else {
            echo $this->content;
        }
    }

    public static function renderPartial(string $view, array $data = []): void
    {
        $__viewData = $data;
        unset($data);
        extract($__viewData, EXTR_SKIP);
        require base_path("www/Views/{$view}.php");
    }
}
