%{

#include <iostream>
#include <stdio.h>
#include <stdlib.h>
#include <set>
#define YYSTYPE string
using namespace std;

extern FILE * yyin;
extern int yylineno;
extern YYSTYPE yylval;
extern char *yytext;
extern int yylex();
void yyerror(const char *s) {
  cerr << s << ", line " << yylineno << " \"" << yylval << "\" \"" << yytext << "\"" << endl;
  exit(1);
}
string trim_block_name(const string &str) {
    return str.substr(1, str.length() - 2);
}
string escape_string(string &str) {
    const string special_chars = "\n'\\";
    size_t pos = str.find_first_of(special_chars, 0);
    while (pos != string::npos) {
        str.insert(pos, "\\");
        pos = str.find_first_of(special_chars, pos+2);
    }
    return "'" + str + "'";
}
set<string> cur_vars;
string className = "";
string writes_to = "OUT";

%}

%token HEADER_OPEN HEADER_CLOSE FOOTER_OPEN FOOTER_CLOSE
%token IF ELSEIF ELSE ENDIF FOR ENDFOR SET WRITE VIS
%token LANG_WORD WORD CMP_OP RAW_STRING STRING NUMBER
%token REST_ARGS

%%

PROGRAM: BLOCKS { cout << "var " + className + " = { \n" + $1 + "\n};\n"; }
;

BLOCKS: BLOCK
| BLOCKS BLOCK { $$ = $1 + ",\n" + $2; }
;

BLOCK: BLOCK_HEADER BLOCK_FOOTER { $$ = $1 + $2; }
| BLOCK_HEADER BLOCK_EXPRS BLOCK_FOOTER { 
    if ($1 != $3) yyerror("Block close tag name missmatch");

    string s = "\"" + $1 + "\": function ($_vis, $_in, $_c) {\n";
    s = s + "var $vars = {};\n";
    if (cur_vars.count("UNIQID")) { // template uses "UNIQID"
        s = s + "$vars['UNIQID'] = parseInt(Math.random()*0xffffffff).toString(16);";
        cur_vars.erase(cur_vars.find("UNIQID"));
    }
    if (cur_vars.size() > 0) { // template contains variables
        for (set<string>::const_iterator it = cur_vars.begin(); it != cur_vars.end(); it++) {
            s = s + "$vars['" + (*it) + "']='';";
        }
        s = s +
            "$vars = $vars.concat($_c);" +
            "for (var i in $_c) $vars['C_'+i] = $_c[i];" +
            "for (var i in $_in) $vars['IN_'+i] = $_in[i];";
    }
    s = s + "$vars['OUT'] = \'\';";
    s = s + $2;
    s = s + "return $vars['OUT']; }";
    cur_vars.clear();
    $$ = s;
}
;

BLOCK_HEADER: HEADER_OPEN STRING HEADER_CLOSE { cur_vars.clear(); writes_to = "OUT"; $$ = trim_block_name($2); }
;

BLOCK_FOOTER: FOOTER_OPEN STRING FOOTER_CLOSE { $$ = trim_block_name($2); }
;

BLOCK_EXPRS: BLOCK_EXPR
| BLOCK_EXPRS BLOCK_EXPR { $$ = $1 + $2; }
;

BLOCK_EXPR: RAW_STRINGS { $$ = "$vars['" + writes_to + "'] += " + escape_string($1) + ";\n"; }
| '{' IF ':' COMPARISIONS '}' IF_BODY '{' ENDIF '}' { $$ = "if (" + $4 + ") {" + $6 + "}"; }
| '{' '!' IF ':' COMPARISIONS '}' IF_BODY '{' ENDIF '}' { $$ = "if (!(" + $5 + ")) {" + $7 + "}"; }
| '{' FOR ':' FOR_ARGS '}' BLOCK_EXPRS '{' ENDFOR '}' { $$ = "for (" + $4 +") {" + $6 + "}"; }
| '{' SET ':' ASSIGNMENTS '}' { $$ = $4 + ";"; }
| '{' WRITE '}' { writes_to = "OUT"; $$ = ""; }
| '{' '!' WRITE '}' { writes_to = "OUT"; $$ = "$vars['" + writes_to + "'] = '';"; }
| '{' WRITE ':' WORD '}' { writes_to = $4; $$ = ""; }
| '{' '!' WRITE ':' WORD '}' { writes_to = $5; $$ = "$vars['" + writes_to + "'] = '';"; }
| VALUE_EXPR { $$ = "$vars['" + writes_to + "'] += " + $1 + ";"; }
;

RAW_STRINGS: RAW_STRING
| RAW_STRINGS RAW_STRING { $$ = $1 + $2; }
;

IF_BODY: IF_BODY_EXPR
| IF_BODY IF_BODY_EXPR { $$ = $1 + $2; }
;

IF_BODY_EXPR: BLOCK_EXPR
| '{' ELSEIF ':' COMPARISIONS '}' { $$ = " } else if (" + $4 + ") { "; }
| '{' '!' ELSEIF ':' COMPARISIONS '}' { $$ = " } else if (!(" + $4 + ")) { "; }
| '{' ELSE '}' { $$ = " } else if (true) { "; }
;

FOR_ARGS: ARG { $$ = "$I=0; $I<=" + $1 + "; $I+=1"; }
| ARG ARG_SEP ARG { $$ = "$I=" + $1 + "; $I<=" + $3 + "; $I+=1"; }
| ARG ARG_SEP ARG ARG_SEP ARG { $$ = "$I=" + $1 + "; $I<=" + $3 + "; $I+=" + $5; }
;

VALUE_EXPR: '{' VALUE_EXPR_BODY '}' { $$ = $2; }
| '{' '!' VALUE_EXPR_BODY '}' { $$ = "$_vis.escapeXML(" + $3 + ")"; }
;

VALUE_EXPR_BODY: VIS ':' WORD MORE_NAMED_ARGS VIS_TAIL { $$ = "$_vis.parseVIS('" + $3 + "', {" + $4 + "}" + $5 + ")"; }
| WORD { $$ = "$" + $1; cur_vars.insert($1); } 
| WORD ':' ARGS { $$ = "$_vis.callParseFunctionArr('" + $1 + "', [" + $3 + "])"; }
| LANG_WORD MORE_ARGS { $$ = "$_vis.lang('" + $1.substr(2, string::npos) + "', [" + $2 + "])"; }
;

VIS_TAIL: /* empty */ { $$ = ""; }
| REST_ARGS { $$ = ", $_in"; }
;

ARG_SEP: '|' | ',';

ARGS: ARG
| ARGS ARG_SEP ARG { $$ = $1 + "," + $3; }
;

MORE_ARGS: /* empty */ { $$ = ""; }
| ARG_SEP ARGS { $$ = $2; }
;

ARG: VALUE_EXPR
| WORD { $$ = "$vars['" + $1 + "']"; cur_vars.insert($1); }
| STRING { $$ = "\"" + $1 + "\""; }
| NUMBER
;

NAMED_ARGS: NAMED_ARG
| NAMED_ARGS ARG_SEP NAMED_ARG { $$ = $1 + "," + $3; }
;

NAMED_ARG: WORD '=' ARG { $$ = "'" + $1 + "':" + $3; }
;

MORE_NAMED_ARGS: /* empty */ { $$ = ""; }
| ARG_SEP NAMED_ARGS { $$ = $2; }
;

COMPARISIONS: COMPARISION
| COMPARISIONS ARG_SEP COMPARISION { $$ = $1 + " && " + $3; }
;

COMPARISION: ARG { $$ = "String(" + $1 + ").length"; }
| ARG COMPARISION_OPERATOR ARG { $$ = $1 + $2 + $3;}
;

COMPARISION_OPERATOR: '=' { $$ = "=="; }
| CMP_OP
;

ASSIGNMENTS: ASSIGNMENT
| ASSIGNMENT ARG_SEP ASSIGNMENTS { $$ = $1 + ";" + $3; }
;

ASSIGNMENT: WORD '=' ARG { $$ = "$vars['" + $1 + "']=" + $3; }
;

%%
int main(int argc, char* argv[]) { 
    if (argc < 3) {
        cout << "Usage: " << argv[0] << " {file name} {class name}\n";
        exit(1);
    }
    yyin = fopen(argv[1], "r");
    className = argv[2];
    return yyparse(); 
}


