<?php
namespace Parse\Commands;

use DOMElement;
use SplQueue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ParseCommand extends Command{

    private const MAIN_WEB = "https://nginx.org/en/docs";

    /**
     * URL列表
     * @var SplQueue
     */
    private $urls;

    /**
     * 已经爬取过的URL
     * @var array
     */
    private $urls_finish = [];

    /**
     * ul 标签的层级
     * @var int
     */
    private $ul_level = 0;

    protected static $defaultName = 'parse';

    protected function configure()
    {
        $this->setDescription("格式化网站");
    }

    public function __construct(string $name = null)
    {
        parent::__construct($name);

        $this->urls = new SplQueue();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->urls->push(self::MAIN_WEB);

        for ($i = 0; $i < 5; $i++) {
            $this->urlsIterator($output);
        }

        $output->writeln("finish");

        return self::SUCCESS;
    }

    /**
     * URL 迭代
     * @return void
     */
    protected function urlsIterator(OutputInterface $output)
    {
        // 当没有的时候直接返回
        if ($this->urls->count() == 0) {
            return;
        }

        $url = $this->urls->shift();

        $output->writeln("url: " . $url);

        $content = $this->getContentByUrl($url);
        $markdown = $this->domToMarkdown($content);

        $file = substr($url, strlen(self::MAIN_WEB));
        $file = trim($file);

        if (empty($file)) {
            $file = "index.md";
        }

        $file = str_replace(".html", '.md', $file);

        file_put_contents(__DIR__ . "/../../en/$file", trim($markdown));
    }

    /**
     * 通过指定 URL 抓取指定文件并进行DOM解析
     * @param string $url
     * @return DOMElement
     */
    protected function getContentByUrl(string $url): DOMElement
    {
        $content = file_get_contents($url);

        $this->urls_finish[] = $url;

        $content = mb_convert_encoding($content, 'HTML-ENTITIES', "UTF-8");

        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML($content);

        // 这里将所有的相对链接转换为绝对链接
        $xpath = new \DOMXPath($dom);
        $a = $xpath->query("//a");

        /** @var DOMElement $item */
        foreach ($a as $item) {
            if ($item->hasAttribute("href")) {
                $href = $item->getAttribute("href");
                if (str_starts_with($href, "http://") === false && str_starts_with($href, "https://") === false) {
                    $href = $this->get_absolute_path($url . "/" . $href);
                    $item->setAttribute("href", $href);
                }
            }
        }

        $xpath = new \DOMXPath($dom);
        $result = $xpath->query("//*[@id='content']");

        return $result->item(0);
    }

    protected function domToMarkdown(DOMElement $dom): string
    {
        $markdown = '';

        /** @var DOMElement $item */
        foreach ($dom->childNodes as $key =>  $item) {
            if ($key == 1 && $item->nodeName == 'table') { // https://nginx.org/en/docs/beginners_guide.html
                continue;
            }

            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        return $markdown;
    }

    protected function parseTable(DOMElement $dom): string
    {
        $markdown = '';

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        if ($markdown) {
            // 自动拼接表头
            $first_line = substr($markdown, 0, strpos($markdown, "\n"));
            $count = substr_count($first_line, "|");

            $markdown = str_repeat("| - ", $count - 1) . "|\n" . str_repeat("|:---:", $count - 1) . "|\n" . $markdown;
        } else {
            $markdown = '';
        }

        return $markdown;
    }

    protected function parseTr(DOMElement $dom): string
    {
        $markdown = '|';

        foreach ($dom->childNodes as $item) {
            $markdown .= str_replace("\n", " ", $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item)) . "|";
        }

        $markdown = trim($markdown);

        return $markdown ? $markdown . "\n" : '';
    }

    protected function parseTd(DOMElement $dom): string
    {
        $markdown = '';

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        $markdown = trim($markdown);

        return $markdown ?: '';
    }

    /**
     * 格式化H2
     * @param DOMElement $dom
     * @return string
     */
    protected function parseH2(DOMElement $dom): string
    {
        $markdown = '## ';

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        $markdown = trim($markdown);

        return $markdown ? $markdown . "\n\n" : '';
    }

    /**
     * 格式化H4
     * @param DOMElement $dom
     * @return string
     */
    protected function parseH4(DOMElement $dom): string
    {
        $markdown = '#### ';

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        $markdown = trim($markdown);

        return $markdown ? $markdown . "\n\n" : '';
    }

    /**
     * 格式化H2
     * @param DOMElement $dom
     * @return string
     */
    protected function parseP(DOMElement $dom): string
    {
        $markdown = '';

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        $markdown = trim($markdown);

        return $markdown ? $markdown . "\n\n" : '';
    }

    protected function parseText(\DOMText $dom): string
    {
        $markdown = '';

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        $markdown .= $dom->wholeText;

        if (str_ends_with($markdown, "\n")) {
            $markdown = rtrim($markdown, "\n") . " ";
        }

        return trim($markdown) ? $markdown : '';
    }

    protected function parseCode($dom): string
    {
        $markdown = '';

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        $markdown = trim($markdown);

        return $markdown ? "`$markdown`": '';
    }

    protected function parseI($dom): string
    {
        $markdown = '';

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        $markdown = trim($markdown);

        return $markdown ? "*$markdown*": '';
    }

    protected function parseDl(DOMElement $dom): string
    {
        $markdown = '';

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        $markdown = trim($markdown);

        return $markdown ? "- $markdown \n": '';
    }

    protected function parseDt(DOMElement $dom): string
    {
        $markdown = '';

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        $markdown = trim($markdown);

        return $markdown ? "**$markdown**": '';
    }

    protected function parseDd(DOMElement $dom): string
    {
        $markdown = '';

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        $markdown = trim($markdown);

        return $markdown ? "\n\n    $markdown\n": '';
    }

    protected function parseBlockquote($dom)
    {
        $markdown = '';

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        $markdown = trim($markdown);

        return preg_replace("/^/m", "> ", $markdown) . "\n\n";
    }

    protected function parsePre($dom): string
    {
        $markdown = '';

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        $markdown = trim($markdown);

        return $markdown ? "```\n{$markdown}\n```\n" : '';
    }

    protected function parseA(DOMElement $dom)
    {
        $markdown = '';

        $link = $dom->getAttribute("href");

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        if (str_ends_with($markdown, "\n")) {
            $markdown = rtrim($markdown, "\n") . " ";
        }

        $markdown = trim($markdown);

        if ($markdown) {
            if (!in_array($link, $this->urls_finish) && str_starts_with($link, self::MAIN_WEB)) {
                $this->urls->push($link);
            }
            return "[$markdown]($link)";
        } else {
            return '';
        }
    }

    protected function parseUl(DOMElement $dom): string
    {
        $markdown = '';

        $this->ul_level++;

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        $markdown .= "\n\n";

        $this->ul_level--;

        return $markdown;
    }

    protected function parseLi(DOMElement $dom): string
    {
        $markdown = '';

        foreach ($dom->childNodes as $item) {
            $li = $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);

            $li = trim($li);

            $markdown .= $li ? "- $li \n" : '';
        }

        return $markdown;
    }

    /**
     * 默认循环
     * @param $dom
     * @return string
     */
    protected function parseDefaultLoop($dom): string
    {
        $markdown = '';

        foreach ($dom->childNodes as $item) {
            $markdown .= $this->{"parse" . ucfirst(ltrim($item->nodeName, '#'))}($item);
        }

        return $markdown;
    }

    /**
     * 替换绝对路径
     * @param $original_path
     * @return string
     */
    private function get_absolute_path($original_path): string
    {
        $parse = parse_url($original_path);

        $path = $parse['path'];

        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');

        $absolutes = array();
        foreach ($parts as $part) {
            if ('.' == $part) {
                continue;
            }
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }

        $absolutes = implode(DIRECTORY_SEPARATOR, $absolutes);

        return substr_replace($original_path, $absolutes, strlen($parse['scheme']) + 3 + strlen($parse['host']) + 1, strlen($parse['path']) - 1);
    }

    public function __call(string $method, $args) {
        if (strpos($method, "parse") === 0 ) {
            $short_method = strtolower(substr($method, 5));
            if (in_array($short_method, ['center', 'nobr', 'br'])) {
                return $this->parseDefaultLoop($args[0]);
            }
        }

        throw new \Error("Call to undefined method " . __CLASS__ . "::" . $method . "()");
    }
}
