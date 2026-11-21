<?php

namespace Tests\Unit;

use App\Core\View;
use Tests\TestCase;

final class ViewTest extends TestCase
{
    public function testRenderExtractsAViewVariableCalledData(): void
    {
        $view = new View();

        ob_start();
        $view->render('../../tests/fixtures/view_data', ['data' => ['key' => 'value']]);
        $output = ob_get_clean();

        self::assertSame('value', $output);
    }

    public function testLayoutWrapsRenderedContent(): void
    {
        $view = new View();
        $view->layout('../../tests/fixtures/layout_data', ['data' => ['key' => 'value']]);

        ob_start();
        $view->render('../../tests/fixtures/view_data', ['data' => ['key' => 'value']]);
        $output = ob_get_clean();

        self::assertSame('LAYOUT:value:value', $output);
    }

    public function testRenderPartialOutputsDirectly(): void
    {
        ob_start();
        View::renderPartial('../../tests/fixtures/view_data', ['data' => ['key' => 'value']]);
        $output = ob_get_clean();

        self::assertSame('value', $output);
    }
}
