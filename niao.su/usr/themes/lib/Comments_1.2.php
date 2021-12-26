<?php

use Typecho\Common;
use Typecho\Config;
use Typecho\Cookie;
use Typecho\Router;
use Typecho\Widget\Helper\PageNavigator\Box;
use Utils\Markdown;
use Widget\Base\Comments;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 评论归档
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 * @version $Id$
 */

/**
 * 评论归档组件
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
/**
 * 评论归档组件
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Mirages_Widget_Comments_Archive extends Comments
{
    /**
     * 当前页
     *
     * @access private
     * @var integer
     */
    private $currentPage;

    /**
     * 所有文章个数
     *
     * @access private
     * @var integer
     */
    private $total = false;

    /**
     * 子父级评论关系
     *
     * @access private
     * @var array
     */
    private $threadedComments = [];

    /**
     * 多级评论回调函数
     *
     * @access private
     * @var mixed
     */
    private $customThreadedCommentsCallback = false;

    /**
     * _singleCommentOptions
     *
     * @var mixed
     * @access private
     */
    private $singleCommentOptions = null;

    private $commentAuthors = array();

    /**
     * @param Config $parameter
     */
    protected function initParameter(Config $parameter)
    {
        $parameter->setDefault('parentId=0&commentPage=0&commentsNum=0&allowComment=1');

        /** 初始化回调函数 */
        if (function_exists('threadedComments')) {
            $this->customThreadedCommentsCallback = true;
        }
    }

    /**
     * 输出文章评论数
     *
     * @param ...$args
     */
    public function num(...$args)
    {
        if (empty($args)) {
            $args[] = '%d';
        }

        $num = intval($this->total);

        echo sprintf($args[$num] ?? array_pop($args), $num);
    }

    /**
     * 执行函数
     *
     * @access public
     * @return void
     */
    public function execute()
    {
        if (!$this->parameter->parentId) {
            return;
        }

        $commentsAuthor = Cookie::get('__typecho_remember_author');
        $commentsMail = Cookie::get('__typecho_remember_mail');
        $select = $this->select()->where('table.comments.cid = ?', $this->parameter->parentId)
            ->where(
                'table.comments.status = ? OR (table.comments.author = ?'
                . ' AND table.comments.mail = ? AND table.comments.status = ?)',
                'approved',
                $commentsAuthor,
                $commentsMail,
                'waiting'
            );
        $threadedSelect = null;

        if ($this->options->commentsShowCommentOnly) {
            $select->where('table.comments.type = ?', 'comment');
        }

        $select->order('table.comments.coid', 'ASC');
        $this->db->fetchAll($select, [$this, 'push']);

        /** 需要输出的评论列表 */
        $outputComments = [];

        /** 如果开启评论回复 */
        if ($this->options->commentsThreaded) {
            foreach ($this->stack as $coid => &$comment) {

                /** 取出父节点 */
                $parent = $comment['parent'];

                /** 如果存在父节点 */
                if (0 != $parent && isset($this->stack[$parent])) {

                    /** 如果当前节点深度大于最大深度, 则将其挂接在父节点上 */
                    if ($comment['levels'] >= 2) {
                        $comment['levels'] = $this->stack[$parent]['levels'];
                        $parent = $this->stack[$parent]['parent'];     // 上上层节点
                        $comment['parent'] = $parent;
                    }

                    /** 计算子节点顺序 */
                    $comment['order'] = isset($this->threadedComments[$parent])
                        ? count($this->threadedComments[$parent]) + 1 : 1;

                    /** 如果是子节点 */
                    $this->threadedComments[$parent][$coid] = $comment;
                } else {
                    $outputComments[$coid] = $comment;
                }

            }

            $this->stack = $outputComments;
        }

        /** 评论排序 */
        if ('DESC' == $this->options->commentsOrder) {
            $this->stack = array_reverse($this->stack, true);
//            $this->threadedComments = array_map('array_reverse', $this->threadedComments);
        }

        /** 评论总数 */
        $this->total = count($this->stack);

        /** 对评论进行分页 */
        if ($this->options->commentsPageBreak) {
            if ('last' == $this->options->commentsPageDisplay && !$this->parameter->commentPage) {
                $this->currentPage = ceil($this->total / $this->options->commentsPageSize);
            } else {
                $this->currentPage = $this->parameter->commentPage ? $this->parameter->commentPage : 1;
            }

            /** 截取评论 */
            $this->stack = array_slice(
                $this->stack,
                ($this->currentPage - 1) * $this->options->commentsPageSize,
                $this->options->commentsPageSize
            );

            /** 评论置位 */
            $this->length = count($this->stack);
            $this->row = $this->length > 0 ? current($this->stack) : [];
        }

        reset($this->stack);
    }

    /**
     * 将每行的值压入堆栈
     *
     * @param array $value 每行的值
     * @return array
     */
    public function push(array $value): array
    {
        $value = $this->filter($value);

        /** 计算深度 */
        if (0 != $value['parent'] && isset($this->stack[$value['parent']]['levels'])) {
            $value['levels'] = $this->stack[$value['parent']]['levels'] + 1;
        } else {
            $value['levels'] = 0;
        }

        $value['realParent'] = $value['parent'];

        /** 重载push函数,使用coid作为数组键值,便于索引 */
        $this->stack[$value['coid']] = $value;
        $this->commentAuthors[$value['coid']] = $value['author'];
        $this->length ++;

        return $value;
    }

    /**
     * 输出分页
     *
     * @access public
     * @param string $prev 上一页文字
     * @param string $next 下一页文字
     * @param int $splitPage 分割范围
     * @param string $splitWord 分割字符
     * @param string|array $template 展现配置信息
     * @return void
     * @throws \Typecho\Widget\Exception
     */
    public function pageNav(
        string $prev = '&laquo;',
        string $next = '&raquo;',
        int $splitPage = 3,
        string $splitWord = '...',
        $template = ''
    ) {
        if ($this->options->commentsPageBreak) {
            $default = [
                'wrapTag'   => 'ol',
                'wrapClass' => 'page-navigator'
            ];

            if (is_string($template)) {
                parse_str($template, $config);
            } else {
                $config = $template ?: [];
            }

            $template = array_merge($default, $config);

            $pageRow = $this->parameter->parentContent;
            $pageRow['permalink'] = $pageRow['pathinfo'];
            $query = Router::url('comment_page', $pageRow, $this->options->index);

            self::pluginHandle()->trigger($hasNav)->pageNav(
                $this->currentPage,
                $this->total,
                $this->options->commentsPageSize,
                $prev,
                $next,
                $splitPage,
                $splitWord,
                $template,
                $query
            );

            if (!$hasNav && $this->total > $this->options->commentsPageSize) {
                /** 使用盒状分页 */
                $nav = new Box($this->total, $this->currentPage, $this->options->commentsPageSize, $query);
                $nav->setPageHolder('commentPage');
                $nav->setAnchor('comments');

                echo '<' . $template['wrapTag'] . (empty($template['wrapClass'])
                        ? '' : ' class="' . $template['wrapClass'] . '"') . '>';
                $nav->render($prev, $next, $splitPage, $splitWord, $template);
                echo '</' . $template['wrapTag'] . '>';
            }
        }
    }

    /**
     * 列出评论
     *
     * @param mixed $singleCommentOptions 单个评论自定义选项
     */
    public function listComments($singleCommentOptions = null)
    {
        //初始化一些变量
        $this->singleCommentOptions = Config::factory($singleCommentOptions);
        $this->singleCommentOptions->setDefault([
            'before'        => '<ol class="comment-list">',
            'after'         => '</ol>',
            'beforeAuthor'  => '',
            'afterAuthor'   => '',
            'beforeDate'    => '',
            'afterDate'     => '',
            'dateFormat'    => $this->options->commentDateFormat,
            'replyWord'     => _t('回复'),
            'commentStatus' => _t('您的评论正等待审核!'),
            'avatarSize'    => 32,
            'defaultAvatar' => null
        ]);
        self::pluginHandle()->trigger($plugged)->listComments($this->singleCommentOptions, $this);

        if (!$plugged) {
            if ($this->have()) {
                echo $this->singleCommentOptions->before;

                while ($this->next()) {
                    $this->threadedCommentsCallback();
                }

                echo $this->singleCommentOptions->after;
            }
        }
    }

    /**
     * 评论回调函数
     */
    private function threadedCommentsCallback()
    {
        $singleCommentOptions = $this->singleCommentOptions;
        if ($this->customThreadedCommentsCallback) {
            return threadedComments($this, $singleCommentOptions);
        }

        $commentClass = '';
        if ($this->authorId) {
            if ($this->authorId == $this->ownerId) {
                $commentClass .= ' comment-by-author';
            } else {
                $commentClass .= ' comment-by-user';
            }
        }
        ?>
        <li itemscope itemtype="http://schema.org/UserComments" id="<?php $this->theId(); ?>" class="comment-body<?php
        if ($this->levels > 0) {
            echo ' comment-child';
            $this->levelsAlt(' comment-level-odd', ' comment-level-even');
        } else {
            echo ' comment-parent';
        }
        $this->alt(' comment-odd', ' comment-even');
        echo $commentClass;
        ?>">
            <div class="comment-author" itemprop="creator" itemscope itemtype="http://schema.org/Person">
                <span
                        itemprop="image">
                    <?php $this->outputAvatar($singleCommentOptions->avatarSize, $singleCommentOptions->defaultAvatar); ?>
                </span>
                <cite class="fn color-main" itemprop="name"><?php $singleCommentOptions->beforeAuthor();
                    $this->author();
                    $singleCommentOptions->afterAuthor(); ?></cite>
            </div>
            <div class="comment-reply">
                <?php $this->reply($singleCommentOptions->replyWord); ?>
            </div>
            <div class="comment-meta">
                <a href="<?php $this->permalink(); ?>">
                    <time itemprop="commentTime"
                          datetime="<?php echo date('c' , $this->created);?>"><?php
                        $singleCommentOptions->beforeDate();
                        $this->date($singleCommentOptions->dateFormat);
                        $singleCommentOptions->afterDate();
                        ?></time>
                </a>
                <?php if ('waiting' == $this->status) { ?>
                    <em class="comment-awaiting-moderation"><?php $singleCommentOptions->commentStatus(); ?></em>
                <?php } ?>
            </div>
            <div class="comment-content" itemprop="commentText">
                <?php $this->text = $this->loadReplyTagByParentCommentId($this->realParent) . $this->text?>
                <?php echo $this->parseCommentContent($this->content); ?>
            </div>
            <?php if ($this->children) { ?>
                <div class="comment-children" itemprop="discusses">
                    <?php $this->threadedComments(); ?>
                </div>
            <?php } ?>
        </li>
        <?php
    }

    private function outputAvatar($size = 32, $default = NULL) {
        if (Mirages::$options->commentsAvatar && !Mirages::$options->embedCommentOptions__disableQQAvatar && preg_match('/^(\d+)@qq.com$/i', $this->mail)) {
            if (NULL != Typecho_Router::get('mirages-api')) {
                $avatar = Typecho_Common::url(Typecho_Router::url('mirages-api', array("action" => "comment-avatar", "pathInfo" => $this->coid . "_" . $size . ".json")), Mirages::$options->index);
                echo '<img class="avatar" src="' . STATIC_PATH . "images/spinner.svg" . '" data-src="' . $avatar .  '" data-type="json" alt="' .
                    $this->author . '" width="' . $size . '" height="' . $size . '" />';
                return;
            }
        }

        $this->gravatar($size, $default);
    }

    /**
     * 调用gravatar输出用户头像
     *
     * @access public
     * @param integer $size 头像尺寸
     * @param string $default 默认输出头像
     * @return void
     */
    public function gravatar($size = 32, $default = NULL) {

        if ($this->options->commentsAvatar && 'comment' == $this->type) {
            $rating = $this->options->commentsAvatarRating;

            Comments::pluginHandle()->trigger($plugged)->gravatar($size, $rating, $default, $this);
            if (!$plugged) {
                $url = Common::gravatarUrl($this->mail, $size, $rating, $default, $this->request->isSecure());
                echo '<img class="avatar" src="' . STATIC_PATH . "images/spinner.svg" . '" data-src="' . $url . '" alt="' .
                    $this->author . '" width="' . $size . '" height="' . $size . '" />';
            }
        }
    }

    /**
     * 输出文章发布日期
     *
     * @access public
     * @param string $format 日期格式
     * @return void
     */
    public function date($format = NULL) {
        echo Utils::formatDate($this->created, 'NATURAL');
    }

    private function loadReplyTagByParentCommentId($parentId):string {
        if ($parentId == "0" || !array_key_exists($parentId, $this->commentAuthors)) {
            return "";
        }
        $author = "@" . $this->commentAuthors[$parentId];
        return '@@MIRAGES_SPAN_START@@' . $author . '@@MIRAGES_SPAN_END@@';
    }


    private function parseCommentContent($content) {
        $content = preg_replace('/@@MIRAGES_SPAN_START@@(.*?)@@MIRAGES_SPAN_END@@/i', '<span class="comment-reply-author">$1</span>', $content);
        return $content;
    }

    /**
     * 根据深度余数输出
     *
     * @param mixed ...$args 需要输出的值
     */
    public function levelsAlt(...$args)
    {
        $num = count($args);
        $split = $this->levels % $num;
        echo $args[(0 == $split ? $num : $split) - 1];
    }

    /**
     * 重载alt函数,以适应多级评论
     *
     * @param ...$args
     */
    public function alt(...$args)
    {
        $num = count($args);

        $sequence = $this->levels <= 0 ? $this->sequence : $this->order;

        $split = $sequence % $num;
        echo $args[(0 == $split ? $num : $split) - 1];
    }

    /**
     * 评论回复链接
     *
     * @param string $word 回复链接文字
     */
    public function reply(string $word = '')
    {
        if ($this->options->commentsThreaded && $this->parameter->allowComment) {
            $word = empty($word) ? _t('回复') : $word;
            self::pluginHandle()->trigger($plugged)->reply($word, $this);

            if (!$plugged) {
                echo '<a href="' . substr($this->permalink, 0, - strlen($this->theId) - 1) . '?replyTo=' . $this->coid .
                    '#' . $this->parameter->respondId . '" rel="nofollow" onclick="return TypechoComment.reply(\'' .
                    $this->theId . '\', ' . $this->coid . ');">' . $word . '</a>';
            }
        }
    }

    /**
     * 递归输出评论
     */
    public function threadedComments()
    {
        $children = $this->children;
        if ($children) {
            //缓存变量便于还原
            $tmp = $this->row;
            $this->sequence ++;

            //在子评论之前输出
            echo $this->singleCommentOptions->before;

            foreach ($children as $child) {
                $this->row = $child;
                $this->threadedCommentsCallback();
                $this->row = $tmp;
            }

            //在子评论之后输出
            echo $this->singleCommentOptions->after;

            $this->sequence --;
        }
    }

    /**
     * 取消评论回复链接
     *
     * @param string $word 取消回复链接文字
     */
    public function cancelReply(string $word = '')
    {
        if ($this->options->commentsThreaded) {
            $word = empty($word) ? _t('取消回复') : $word;
            self::pluginHandle()->trigger($plugged)->cancelReply($word, $this);

            if (!$plugged) {
                $replyId = $this->request->filter('int')->replyTo;
                echo '<a id="cancel-comment-reply-link" href="' . $this->parameter->parentContent['permalink'] . '#' . $this->parameter->respondId .
                    '" rel="nofollow"' . ($replyId ? '' : ' style="display:none"') . ' onclick="return TypechoComment.cancelReply();">' . $word . '</a>';
            }
        }
    }

    /**
     * 获取当前评论链接
     *
     * @return string
     */
    protected function ___permalink(): string
    {

        if ($this->options->commentsPageBreak) {
            $pageRow = ['permalink' => $this->parentContent['pathinfo'], 'commentPage' => $this->currentPage];
            return Router::url('comment_page', $pageRow, $this->options->index) . '#' . $this->theId;
        }

        return $this->parentContent['permalink'] . '#' . $this->theId;
    }

    /**
     * 子评论
     *
     * @return array
     */
    protected function ___children(): array
    {
        return $this->options->commentsThreaded && !$this->isTopLevel && isset($this->threadedComments[$this->coid])
            ? $this->threadedComments[$this->coid] : [];
    }

    /**
     * 是否到达顶层
     *
     * @return boolean
     */
    protected function ___isTopLevel(): bool
    {
//        return $this->levels > $this->options->commentsMaxNestingLevels - 2;
        return $this->levels > 0;
    }

    /**
     * 重载内容获取
     *
     * @return array|null
     */
    protected function ___parentContent(): ?array
    {
        return $this->parameter->parentContent;
    }

    /**
     * 获取当前评论内容
     *
     * @return string|null
     */
    protected function ___content(): ?string
    {
        $text = $this->parentContent['hidden'] ? _t('内容被隐藏') : $this->text;

        $text = Comments::pluginHandle()->trigger($plugged)->content($text, $this);
        if (!$plugged) {
            $text = $this->options->commentsMarkdown ? $this->markdown($text)
                : $this->autoP($text);
        }

        $text = Comments::pluginHandle()->contentEx($text, $this);
        return Common::stripTags($text, '<p><br>' . $this->options->commentsHTMLTagAllowed);
    }

    /**
     * markdown
     *
     * @param mixed $text
     * @access public
     * @return string
     */
    public function markdown(?string $text): ?string
    {
        $html = Comments::pluginHandle()->trigger($parsed)->markdown($text);
        if (!$parsed) {
            $text = preg_replace('/\#\[\s*(呵呵|哈哈|吐舌|太开心|笑眼|花心|小乖|乖|捂嘴笑|滑稽|你懂的|不高兴|怒|汗|黑线|泪|真棒|喷|惊哭|阴险|鄙视|酷|啊|狂汗|what|疑问|酸爽|呀咩爹|委屈|惊讶|睡觉|笑尿|挖鼻|吐|犀利|小红脸|懒得理|勉强|爱心|心碎|玫瑰|礼物|彩虹|太阳|星星月亮|钱币|茶杯|蛋糕|大拇指|胜利|haha|OK|沙发|手纸|香蕉|便便|药丸|红领巾|蜡烛|音乐|灯泡|开心|钱|咦|呼|冷|生气|弱|吐血)\s*\]/is',
                "@($1)", $text);
            $text = preg_replace('/\#\(\s*(高兴|小怒|脸红|内伤|装大款|赞一个|害羞|汗|吐血倒地|深思|不高兴|无语|亲亲|口水|尴尬|中指|想一想|哭泣|便便|献花|皱眉|傻笑|狂汗|吐|喷水|看不见|鼓掌|阴暗|长草|献黄瓜|邪恶|期待|得意|吐舌|喷血|无所谓|观察|暗地观察|肿包|中枪|大囧|呲牙|抠鼻|不说话|咽气|欢呼|锁眉|蜡烛|坐等|击掌|惊喜|喜极而泣|抽烟|不出所料|愤怒|无奈|黑线|投降|看热闹|扇耳光|小眼睛|中刀)\s*\)/is',
                "\\#($1)", $text);


            $html = Markdown::convert($text);
        }

        return $html;
    }
}
