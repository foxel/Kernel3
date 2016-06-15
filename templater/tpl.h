#include <string>

#ifndef TPL_H_INCLUDED
# define TPL_H_INCLUDED
using namespace std;

#define YYSTYPE string

class tpl_translator {
    public:
        virtual void program(string blocks) = 0;

        virtual string blocks(string blocks, string block) = 0;

        virtual string blocks(string block) = 0;

        virtual string block(string block_header, string block_footer) = 0;

        virtual string block(string block_header, string block_footer, string body) = 0;

        virtual string block_header(string name) = 0;

        virtual string block_footer(string name) = 0;

        virtual string block_exprs(string block_expr) = 0;

        virtual string block_exprs(string block_exprs, string block_expr) = 0;

        virtual string block_expr_raw(string raw_strings) = 0;

        virtual string block_expr_if(bool negate, string condition, string if_body) = 0;

        virtual string block_expr_for(string for_args, string block_exprs) = 0;

        virtual string block_expr_set(string assignments) = 0;

        virtual string block_expr_write(bool erase) = 0;

        virtual string block_expr_write(bool erase, string target) = 0;

        virtual string block_expr_value(string value_expr) = 0;

        virtual string raw_strings(string raw_string) = 0;

        virtual string raw_strings(string raw_strings, string raw_string) = 0;

        virtual string if_body(string if_body_expr) = 0;

        virtual string if_body(string if_body, string if_body_expr) = 0;

        virtual string if_body_expr_block(string block_expr) = 0;

        virtual string if_body_expr_elseif(bool negate, string condition) = 0;

        virtual string if_body_expr_else(void) = 0;

        virtual string for_args(string arg1) = 0;

        virtual string for_args(string arg1, string arg2) = 0;

        virtual string for_args(string arg1, string arg2, string arg3) = 0;

        virtual string value_expr(bool escape, string valur_expr_body) = 0;

        virtual string value_expr_body_variable(string name) = 0;

        virtual string value_expr_body_vis(string name, string args, string tail) = 0;

        virtual string value_expr_body_call(string name, string args) = 0;

        virtual string value_expr_body_lang(string name, string args) = 0;

        virtual string vis_tail_none() = 0;

        virtual string vis_tail_rest() = 0;

        virtual string args(void) = 0;

        virtual string args(string arg) = 0;

        virtual string args(string args, string arg) = 0;

        virtual string arg_value(string value_expr) = 0;

        virtual string arg_variable(string name) = 0;

        virtual string arg_raw(string raw_string) = 0;

        virtual string arg_numeric(string number) = 0;

        virtual string named_args(void) = 0;

        virtual string named_args(string named_arg) = 0;

        virtual string named_args(string named_args, string named_arg) = 0;

        virtual string named_arg(string name, string arg) = 0;

        virtual string conditions(string condition) = 0;

        virtual string conditions(string conditions, string condition) = 0;

        virtual string condition(string arg) = 0;

        virtual string condition(string condition_operator, string arg1, string arg2) = 0;

        virtual string condition_operator(string op) = 0;

        virtual string assignments(string assignment) = 0;

        virtual string assignments(string assignments, string assignment) = 0;

        virtual string assignment(string name, string arg) = 0;

};

#endif /* !TPL_H_INCLUDED  */
