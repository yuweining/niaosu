<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Settings.php
 * Author     : Hran
 * Date       : 2017/3/26
 * Version    :
 * Description:
 */
class Mirages_Settings {

    private static $instance = NULL;

    private $themeOptions = NULL;
    private $priorityRevertKeys = array('k', 'init', 'state');
    private $localStorage = array('init' => 'License', 'state' => false);
    /**
     * @var Widget_User|mixed
     */
    public $currentUser = NULL;

    private static function init() {
        self::$instance = new Mirages_Settings();
        self::$instance->themeOptions = Helper::options();
        self::$instance->currentUser = Typecho_Widget::widget('Widget_User');
    }

    /**
     * @return Mirages_Settings|mixed
     */
    public static function instance() {
        if (self::$instance == NULL) {
            self::init();
        }
        return self::$instance;
    }

    public function __get($key) {
        return $this->get($key);
    }

    public function get($key, $default = NULL) {
        if (in_array($key, $this->priorityRevertKeys)) {
            $value = $this->themeOptions->{$key};
            if (NULL == $value && $value !== FALSE && array_key_exists($key, $this->localStorage)) {
                $value = $this->localStorage[$key];
            }
        } else {
            $value = array_key_exists($key, $this->localStorage) ? $this->localStorage[$key] : $this->themeOptions->{$key};
        }
        if (NULL == $value && $value !== FALSE) {
            if (!strpos($key, '__') > 0) {
                return $default;
            }
            $key = preg_split('/__/i', $key, 2);
            $option = $key[0];
            $value = @$key[1];
            $option = $this->get($option);
            if (!empty($value) && method_exists('Utils', $value)) {
                return Utils::$value($option);
            }
            if (is_array($option) && !empty($value)) {
                if (in_array($value, $option)) {
                    return true;
                }
                return false;
            }
            return NULL;
        }
        return $value;
    }

    public function __set($key, $value) {
        $this->set($key, $value);
    }

    public function set() {
        if (func_num_args() >= 1) {
            $args = func_get_args();
            $key = $args[0];
            array_shift($args);
            if (empty($args)) {
                unset($this->localStorage[$key]);
            } elseif (count($args) == 1) {
                $value = $args[0];
                if ($value == NULL) {
                    unset($this->localStorage[$key]);
                } else {
                    $this->localStorage[$key] = $value;
                }
            } else {
                $this->localStorage[$key] = $args;
            }
        }
    }

    public function loadSettings($props) {
        $props = mb_split("\n", $props);
        foreach ($props as $prop) {
            $item = mb_split("=", $prop, 2);
            if (is_array($item) && count($item) == 2) {
                $key = trim($item[0]);
                if (Utils::startsWith($key, "#")) {
                    continue;
                }
                if (strpos($key, '.')) {
                    $key = mb_split("\.", $key, 2);
                    if (is_array($key) && count($key) == 2) {
                        $key = $key[0] . '__' . $key[1];
                    }
                }
                $value = trim($item[1]);
                $this->localStorage[$key] = $value;
            }
        }
    }
    public function loadDefaultSettings() {
        $this->localStorage["devMode"] = (defined("MIRAGES_DEVELOPER") && MIRAGES_DEVELOPER) ? 1 : 0;
        $this->localStorage["localeFontFamily"] = "'PingFang SC', 'Hiragino Sans GB', 'Microsoft Yahei', 'WenQuanYi Micro Hei'";
        $this->localStorage["localeSerifFontFamily"] = "'Noto Serif CJK SC', 'Noto Serif CJK', 'Noto Serif SC', 'Source Han Serif SC', 'Source Han Serif', 'source-han-serif-sc', 'PT Serif', 'SongTi SC', 'MicroSoft Yahei'";
    }

    public function __call($name, $args) {
        echo $this->get($name, @$args[0]);
    }
}