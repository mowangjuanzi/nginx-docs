# nginx-docs
尝试做文档同步

## 目录结构

- `parse` 使用代码对 `http://nginx.org/en/docs/` 进行格式化，并将转换后的文档存入 `en`
- `en` 存放 nginx document 源码
- `zh` 存放中文翻译后的文档

## 抓取目录

执行以下命令即可：

```bash
cd parse
composer update
php index.php parse
```

目前还在调试中，暂时无法完整抓取。