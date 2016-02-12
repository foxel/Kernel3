templater: tpl.tab.c tpl.yy.c
	g++ tpl.tab.c tpl.yy.c -o templater

tpl.yy.c: tpl.lex
	flex -o tpl.yy.c tpl.lex

tpl.tab.c: tpl.y
	bison -d tpl.y


