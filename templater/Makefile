all: tpl2php tpl2js

tpl2php: tpl.tab.cpp tpl.yy.cpp tpl2php.cpp
	g++ tpl.tab.cpp tpl.yy.cpp tpl2php.cpp -o tpl2php

tpl2js: tpl.tab.cpp tpl.yy.cpp tpl2js.cpp
	g++ tpl.tab.cpp tpl.yy.cpp tpl2js.cpp -o tpl2js

tpl.yy.cpp: tpl.lex
	flex -o tpl.yy.cpp tpl.lex

tpl.tab.cpp: tpl.y
	bison -o tpl.tab.cpp --defines=tpl.tab.h tpl.y

clean:
	rm -f tpl2php tpl2js *.yy.cpp *.tab.cpp *.tab.h
