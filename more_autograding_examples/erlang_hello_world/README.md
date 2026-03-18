# Erlang Hello World Autograding Example

This example demonstrates autograding for Erlang programs using Docker.

## Overview

Students are asked to create an Erlang module `hello` with a function `hello_world/0` that prints "hello world" to standard output.

## Files

- **config/config.json**: Autograding configuration file
- **submissions/correct.erl**: Example correct submission
- **submissions/incorrect.erl**: Example incorrect submission

## Grading Breakdown

- **Compilation (2 points)**: The Erlang source file must compile successfully using `erlc`
- **Output Check (3 points)**: The program must output "hello world" followed by a newline

## Docker Image

This example uses the official `erlang:26` Docker image from Docker Hub.

**Note**: Most other Submitty autograding examples use custom `submitty/<language>` Docker images. For consistency with the Submitty ecosystem, a `submitty/erlang` image could be created in the future. However, the official Erlang image works well for this example.

## Student Instructions

Create a file named `hello.erl` containing:
- A module declaration: `-module(hello).`
- An export declaration: `-export([hello_world/0]).`
- A function `hello_world/0` that prints "hello world" to stdout using `io:format/1`

## Example Solution

```erlang
-module(hello).
-export([hello_world/0]).

hello_world() -> 
    io:format("hello world~n").
```

## Testing Locally

To test the Erlang program locally:

```bash
erlc hello.erl
erl -noshell -s hello hello_world -s init stop
```

Expected output: `hello world`

## Notes

- In Erlang, the module name should match the filename (without the `.erl` extension); for your submission, use `hello.erl` for the `hello` module
- Erlang uses `~n` for newlines in `io:format/1` rather than `\n`
- The `-noshell` flag runs Erlang without an interactive shell
- The `-s init stop` flag ensures the Erlang VM terminates after running the function
