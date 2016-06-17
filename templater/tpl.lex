%{
#include <string>
#include <iostream>
#include <algorithm>
#define YYSTYPE std::string
#include "tpl.tab.h"
using namespace std;

string str_toupper(string str) {
    transform(str.begin(), str.end(), str.begin(), ::toupper);
    return str;
}
void yyerror(const char *s);
int code_depth = 0;
int str_parent = 0;

%}

%option yylineno
%option noyywrap

%x FILE_COMMENT
%x FILE_REQUIRE
%x BLOCK_HEADER
%x BLOCK_FOOTER
%x BLOCK_BODY
%x CODE_PART
%x STR

%%

[<][<][+]       { BEGIN(BLOCK_HEADER); return HEADER_OPEN; }
[<]!--          BEGIN(FILE_COMMENT);
[@]require      { BEGIN(FILE_REQUIRE); return REQUIRE; }
[ \r\n\t]       ;
.               yyerror("Invalid character");

<FILE_COMMENT>-->   BEGIN(INITIAL);
<FILE_COMMENT>.     ;

<FILE_REQUIRE>[^\r\n "]*    { yylval = yytext; return STRING; }
<FILE_REQUIRE>[\r\n]*       BEGIN(INITIAL);
<FILE_REQUIRE>["]           { yylval = ""; str_parent=FILE_REQUIRE; BEGIN(STR); }
<FILE_REQUIRE>[\t ]*        ;

<BLOCK_HEADER>['][a-zA-Z_][a-zA-Z0-9_]*[']  { yylval = str_toupper(yytext);
                  return STRING;
                }
<BLOCK_HEADER>[>][>][ \n\r\t]* { BEGIN(BLOCK_BODY); return HEADER_CLOSE; }
<BLOCK_HEADER>[ ]*          ;

<BLOCK_FOOTER>['][a-zA-Z_][a-zA-Z0-9_]*[']  { yylval = yytext;
                  return STRING;
                }
<BLOCK_FOOTER>[>][>]        { BEGIN(INITIAL); return FOOTER_CLOSE; }
<BLOCK_FOOTER>[ ]*          ;

<BLOCK_BODY>[\r\n]*         ;
<BLOCK_BODY>[<][<][-]       { BEGIN(BLOCK_FOOTER); return FOOTER_OPEN; }

<BLOCK_BODY>[^{<]*          { yylval = yytext; return RAW_STRING; }
<BLOCK_BODY>[{][ \n\t]      { yylval = yytext; return RAW_STRING; }
<BLOCK_BODY>[{]             { code_depth = 1; BEGIN(CODE_PART); return *yytext; }
<BLOCK_BODY>.               { yylval = yytext; return RAW_STRING; }

<CODE_PART>==|!=|>|<|>=|<=  { yylval = yytext; return CMP_OP; }
<CODE_PART>[|]_             { return REST_ARGS; }
<CODE_PART>[_=:|!]          { return *yytext; }

<CODE_PART>IF|if            { return IF; }
<CODE_PART>ELSEIF|elseif    { return ELSEIF; }
<CODE_PART>ELSE|else        { return ELSE; }
<CODE_PART>ENDIF|[/]IF|endif|[/]if    { return ENDIF; }
<CODE_PART>FOR|for          { return FOR; }
<CODE_PART>ENDFOR|endfor|[/]FOR|[/]for  { return ENDFOR; }
<CODE_PART>SET|set          { return SET; }
<CODE_PART>WRITE|write      { return WRITE; }
<CODE_PART>VIS|vis          { return VIS; }
<CODE_PART>L_[a-zA-Z][a-zA-Z0-9_]*      { yylval = str_toupper(yytext); return LANG_WORD; }
<CODE_PART>[a-zA-Z][a-zA-Z0-9_]*        { yylval = str_toupper(yytext); return WORD; }
<CODE_PART>[-]?[0-9]+([.][0-9]+)?       { yylval = yytext; return NUMBER; }
<CODE_PART>["]              { yylval = ""; str_parent=CODE_PART; BEGIN(STR); }
<CODE_PART>[ ]              ;
<CODE_PART>[{]              { code_depth++; return *yytext; }
<CODE_PART>[}]              { code_depth--; if (code_depth == 0) BEGIN(BLOCK_BODY); return *yytext; }

<STR>[^\\\n"]+  yylval += yytext;
<STR>\\n        yylval += '\n';
<STR>\\["]      yylval += '"';
<STR>\\         yyerror("Invalid escape sequence");
<STR>\n         yyerror("Newline in string literal");
<STR>["]        { BEGIN(str_parent); return STRING; }


%%
