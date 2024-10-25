% rename to main.pl before submitting
% i'm working on seeing if there's a way to allow any name
% but it might just need to be some grep silliness

:- use_module(knowledge_base).

goal(X) :- fact(X),fact2(X),fact3(X),fact4(X).