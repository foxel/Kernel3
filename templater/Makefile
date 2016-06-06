all: tpl2php tpl2js

tpl2php: tpl2php.tab.cpp tpl.yy.cpp
	g++ tpl2php.tab.cpp tpl.yy.cpp -o tpl2php

tpl2js: tpl2js.tab.cpp tpl.yy.cpp
	g++ tpl2js.tab.cpp tpl.yy.cpp -o tpl2js

tpl.yy.cpp: tpl.lex
	flex -o tpl.yy.cpp tpl.lex

tpl2php.tab.cpp: tpl2php.y
	bison -o tpl2php.tab.cpp --defines=tpl.tab.h tpl2php.y

tpl2js.tab.cpp: tpl2js.y
	bison -o tpl2js.tab.cpp --defines=tpl.tab.h tpl2js.y

clean:
	rm -f *.cpp *.h
