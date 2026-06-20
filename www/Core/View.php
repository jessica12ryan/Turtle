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
        extract($data, EXTR_OVERWRITE);

        ob_start();
        require base_path("www/Views/{$view}.php");
        $this->content = ob_get_clean();

        if ($this->layout) {
            $data = $this->layoutData;
            $data['content'] = $this->content;
            extract($data, EXTR_OVERWRITE);
            require base_path("www/Views/{$this->layout}.php");
        } else {
            echo $this->content;
        }
    }

    public static function renderPartial(string $view, array $data = []): void
    {
        extract($data, EXTR_OVERWRITE);
        require base_path("www/Views/{$view}.php");
    }
}
