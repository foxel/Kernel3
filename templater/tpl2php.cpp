#include <iostream>
#include <stdio.h>
#include <stdlib.h>
#include <set>
#include "tpl.h"
#include "tpl.tab.h"

extern FILE * yyin;
extern int yyparse (void);

extern int yylineno;
extern YYSTYPE yylval;
extern char *yytext;

tpl_translator *translator;


void yyerror(const char *s) {
  cerr << s << ", line " << yylineno << " \"" << yylval << "\" \"" << yytext << "\"" << endl;
  exit(1);
}

class tpl2php_translator : public tpl_translator {
    private:
        set<string> cur_vars;
        string className;
        string writes_to;
        
        string trim_block_name(const string &str) {
            return str.substr(1, str.length() - 2);
        }
        string escape_string(string &str) {
            string nowdocId = "FTEXT";
            while (str.find(nowdocId) != string::npos) {
                nowdocId = nowdocId + "T";
            }
            return "<<<'" + nowdocId + "'\n" + str + "\n" + nowdocId;
        }

    public:
        tpl2php_translator(string _className) {
            writes_to = "OUT";
            className = _className;
        }

        void program(string blocks) {
            cout << "class " + className + " { \n" + blocks + "\n}\n";
        }

        string blocks(string blocks, string block) {
            return blocks + block;
        }

        string blocks(string block) {
            return block;
        }

        string block(string block_header, string block_footer) {
            //if (block_header != block_footer) yyerror("Block close tag name missmatch");

            string s = "function " + block_header + "(FVISInterface $_vis, $_in, $_c) { return ''; }";
            cur_vars.clear();
            return s;
        }

        string block(string block_header, string block_footer, string body) {
            //if (block_header != block_footer) yyerror("Block close tag name missmatch");

            string s = "function " + block_header + "(FVISInterface $_vis, $_in, $_c) {\n";
            if (cur_vars.count("UNIQID")) { // template uses "UNIQID"
                s = s + "$UNIQID = dechex(mt_rand(0x1FFF, getrandmax()));";
                cur_vars.erase(cur_vars.find("UNIQID"));
            }
            if (cur_vars.size() > 0) { // template contains variables
                for (set<string>::const_iterator it = cur_vars.begin(); it != cur_vars.end(); it++) {
                    s = s + "$" + (*it) + "='';";
                }
                s = s +
                    "extract($_c, EXTR_OVERWRITE);" +
                    "extract($_c, EXTR_OVERWRITE | EXTR_PREFIX_ALL, \'C\');" + 
                    "extract($_in, EXTR_OVERWRITE | EXTR_PREFIX_ALL, 'IN');";
            }
            s = s + "$OUT = \'\';";
            s = s + body;
            s = s + "return $OUT; }";
            cur_vars.clear();
            return s;
        }

        string block_header(string name) {
            cur_vars.clear();
            writes_to = "OUT";
            return trim_block_name(name);
        }

        string block_footer(string name) {
            return trim_block_name(name);
        }

        string block_exprs(string block_expr) {
            return block_expr;
        }

        string block_exprs(string block_exprs, string block_expr) {
            return block_exprs + block_expr;
        }

        string block_expr_raw(string raw_strings) {
            return "$" + writes_to + " .= " + escape_string(raw_strings) + ";\n";
        }

        string block_expr_if(bool negate, string condition, string if_body) {
            return negate
                ? "if (" + condition + ") {" + if_body + "}"
                : "if (!(" + condition + ")) {" + if_body + "}";
        }

        string block_expr_for(string for_args, string block_exprs) {
            return "for (" + for_args +") {" + block_exprs + "}";
        }

        string block_expr_set(string assignments) {
            return assignments;
        }

        string block_expr_write(bool erase) {
            return block_expr_write(erase, "OUT");
        }

        string block_expr_write(bool erase, string target) {
            writes_to = target;
            return erase
                ? "$" + writes_to + " = '';"
                : "";
        }

        string block_expr_value(string value_expr) {
            return "$" + writes_to + " .= " + value_expr + ";";
        }

        string raw_strings(string raw_string) {
            return raw_string;
        }

        string raw_strings(string raw_strings, string raw_string) {
            return raw_strings + raw_string;
        }

        string if_body(string if_body_expr) {
            return if_body_expr;
        }

        string if_body(string if_body, string if_body_expr) {
            return if_body + if_body_expr;
        }

        string if_body_expr_block(string block_expr) {
            return block_expr;
        }

        string if_body_expr_elseif(bool negate, string condition) {
            return negate
                ? " } elseif (!(" + condition + ")) { "
                : " } elseif (" + condition + ") { ";
        }

        string if_body_expr_else(void) {
            return " } elseif (true) { ";
        }

        string for_args(string arg1) {
            return for_args("0", arg1, "1");
        }

        string for_args(string arg1, string arg2) {
            return for_args(arg1, arg2, "1");
        }

        string for_args(string arg1, string arg2, string arg3) {
            return "$I=" + arg1 + "; $I<=" + arg2 + "; $I+=" + arg3;
        }

        string value_expr(bool escape, string valur_expr_body) {
            return escape
                ? "K3_Util_String::escapeXML(" + valur_expr_body + ")"
                : valur_expr_body;
        }

        string value_expr_body_vis(string name, string args, string tail) {
            return "$_vis->parseVIS('" + name + "', array(" + args + ")" + tail + ")";
        }

        string value_expr_body_variable(string name) {
            cur_vars.insert(name);
            return "$" + name;
        }

        string value_expr_body_call(string name, string args) {
            return "$_vis->callParseFunctionArr('" + name + "', array(" + args + "))";
        }

        string value_expr_body_lang(string name, string args) {
            return "F()->LNG->lang('" + name.substr(2, string::npos) + "', array(" + args + "))";
        }

        string vis_tail_none() {
            return "";
        }

        string vis_tail_rest() {
            return " + $_in";
        }

        string args(void) {
            return "";
        }

        string args(string arg) {
            return arg;
        }

        string args(string args, string arg) {
            return args + "," + arg;
        }

        string arg_value(string value_expr) {
            return value_expr;
        }

        string arg_variable(string name) {
            cur_vars.insert(name);
            return "$" + name;
        }

        string arg_raw(string raw_string) {
            return "\"" + raw_string + "\"";
        }

        string arg_numeric(string number) {
            return number;
        }

        string named_args() {
            return "";
        }

        string named_args(string named_arg) {
            return named_arg;
        }

        string named_args(string named_args, string named_arg) {
            return named_args + "," + named_arg;
        }

        string named_arg(string name, string arg) {
            return "'" + name + "'=>" + arg;
        }

        string conditions(string condition) {
            return condition;
        }

        string conditions(string conditions, string condition) {
            return conditions + " && " + condition;
        }

        string condition(string arg) {
            return "strlen(" + arg + ")";
        }

        string condition(string condition_operator, string arg1, string arg2) {
            return arg1 + condition_operator + arg2;
        }

        string condition_operator(string op) {
            return op;
        }

        string assignments(string assignment) {
            return assignment;
        }

        string assignments(string assignments, string assignment) {
            return assignments + assignment;
        }

        string assignment(string name, string arg) {
            return "$" + name + "=" + arg + ";";
        }
};


int main(int argc, char* argv[]) { 
    if (argc < 3) {
        cout << "Usage: " << argv[0] << " {file name} {class name}\n";
        exit(1);
    }
    cout << "<?php\n";
    yyin = fopen(argv[1], "r");
    translator = new tpl2php_translator(argv[2]);
    return yyparse(); 
}
