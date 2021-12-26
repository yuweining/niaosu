<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

use Typecho\Widget\Helper\Form\Element;
use Typecho\Widget\Helper\Layout;

/**
 * Title.php
 * Author     : Hran
 * Date       : 2016/12/10
 * Version    :
 * Description:
 */
class CollapseTitle extends Element
{
    private $labelValue;
    private $options;
    private $descriptionValue;

    public function label(string $value): Element {
        $this->labelValue = $value;
        return $this;
    }

    public function input(?string $name = null, ?array $options = null): Layout {

        if (is_array($options)) {
            $this->options = $options;
        } else {
            $this->options = array("open", "close");
        }

        return new Layout('p', array());
    }

    public function description(string $description): Element {
        $this->descriptionValue = $description;

        return parent::description($description);
    }

    protected function inputValue($value) {}

    public function render() {
        if (in_array('close', $this->options)) {
            echo <<<HTML
            </div>
        </div>
HTML;

        }
        if (in_array('open', $this->options)) {
            echo <<<HTML
        <div class="collapse-block collapse-block-{$this->name}">
            <div class="collapse-header" data-toggle="collapse-block" data-target="#{$this->name}">
                <h2 class="title">{$this->labelValue}</h2>
            </div>
            <div id="{$this->name}" class="collapse-content collapse">
HTML;

            if (!empty($this->descriptionValue)) {
                echo <<<HTML
                    <div class="description">{$this->descriptionValue}</div>
HTML;
            }
        }

    }

}