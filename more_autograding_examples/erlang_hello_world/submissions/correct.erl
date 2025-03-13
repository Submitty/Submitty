-module(hello).
-export([hello_world/0]).

hello_world() -> io:format("hello world\n").

