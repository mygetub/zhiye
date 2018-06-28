<?php

/*
 * This file is part of Hifone.
 *
 * (c) Hifone.com <hifone@hifone.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hifone\Http\Controllers;

use Hifone\Models\Page;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends Controller
{
    public function show($slug)
    {
        $page = Page::where('slug', $slug)->first();
        if (!$page) {
            throw new NotFoundHttpException();
        }
        if($slug == 'markdown')
        {
            $str = '# Guide

这是一篇讲解如何正确使用 Hifone 的 **Markdown** 的排版示例，学会这个很有必要，能让你的文章有更佳清晰的排版。

> 引用文本：Markdown is a text formatting syntax inspired

## 语法指导

### 普通内容

这段内容展示了在内容里面一些小的格式，比如：

- **加粗** - `**加粗**`
- *倾斜* - `*倾斜*`
- ~~删除线~~ - `~~删除线~~`
- `Code 标记` - ``Code 标记``
- [超级链接](http://github.com) - `[超级链接](http://github.com)`
- [hifone@hifone.com](mailto:hifone@hifone.com) - `[hifone@hifone.com](mailto:hifone@hifone.com)`

### 提及用户

@Hifone @Phecho ... 通过 @ 可以在发帖和回帖里面提及用户，信息提交以后，被提及的用户将会收到系统通知。以便让他来关注这个帖子或回帖。

### 大标题 - Heading 3

你可以选择使用 H2 至 H6，使用 ##(N) 打头，H1 不能使用，会自动转换成 H2。

> NOTE: 别忘了 # 后面需要有空格！

#### Heading 4

##### Heading 5

###### Heading 6

### 图片

```
![alt 文本](http://image-path.png)
![alt 文本](http://image-path.png "图片 Title 值")
![设置图片宽度高度](http://image-path.png =300x200)
![设置图片宽度](http://image-path.png =300x)
![设置图片高度](http://image-path.png =x200)
```

### 有序、无序列表

#### 无序列表

- PHP
  - Laravel
  - Yii
- Node.js
  - Koa
  - Express

#### 有序列表

1. Hifone
  1. foo
  2. bar
  3. baz
2. Laravel
  1. qux
  2. quux
3. Yii

### 表格

如果需要展示数据什么的，可以选择使用表格哦

| header 1 | header 3 |
| -------- | -------- |
| cell 1   | cell 2   |
| cell 3   | cell 4   |
| cell 5   | cell 6   |

### 段落

留空白的换行，将会被自动转换成一个段落，会有一定的段落间距，便于阅读。

请注意后面 Markdown 源代码的换行留空情况。';
            return $this->view('pages.markdown')
                ->withPage($page)
                ->with('bodyMsg',$str);
        }
        return $this->view('pages.show')
            ->withPage($page);
    }

}
