%{

#include "tpl.h"

extern tpl_translator *translator;

extern int yylex();

extern void yyerror(const char *s);

%}

%token HEADER_OPEN HEADER_CLOSE FOOTER_OPEN FOOTER_CLOSE
%token IF ELSEIF ELSE ENDIF FOR ENDFOR SET WRITE VIS
%token LANG_WORD WORD CMP_OP RAW_STRING STRING NUMBER
%token REST_ARGS

%%

PROGRAM: BLOCKS { translator->program($1); }
;

BLOCKS: BLOCK { $$ = translator->blocks($1); }
| BLOCKS BLOCK { $$ = translator->blocks($1, $2); }
;

BLOCK: BLOCK_HEADER BLOCK_FOOTER { $$ = translator->block($1, $2); }
| BLOCK_HEADER BLOCK_EXPRS BLOCK_FOOTER { $$ = translator->block($1, $3, $2); }
;

BLOCK_HEADER: HEADER_OPEN STRING HEADER_CLOSE { $$ = translator->block_header($2); }
;

BLOCK_FOOTER: FOOTER_OPEN STRING FOOTER_CLOSE { $$ = translator->block_footer($1); }
;

BLOCK_EXPRS: BLOCK_EXPR { $$ = translator->block_exprs($1); }
| BLOCK_EXPRS BLOCK_EXPR { $$ = translator->block_exprs($1, $2); }
;

BLOCK_EXPR: RAW_STRINGS { $$ = translator->block_expr_raw($1); }
| '{' IF ':' CONDITIONS '}' IF_BODY '{' ENDIF '}' { $$ = translator->block_expr_if(false, $4, $6); }
| '{' '!' IF ':' CONDITIONS '}' IF_BODY '{' ENDIF '}' { $$ = translator->block_expr_if(true, $5, $7); }
| '{' FOR ':' FOR_ARGS '}' BLOCK_EXPRS '{' ENDFOR '}' { $$ = translator->block_expr_for($4, $6); }
| '{' SET ':' ASSIGNMENTS '}' { $$ = translator->block_expr_set($4); }
| '{' WRITE '}' { $$ = translator->block_expr_write(false); }
| '{' '!' WRITE '}' { $$ = translator->block_expr_write(true); }
| '{' WRITE ':' WORD '}' { $$ = translator->block_expr_write(false, $4); }
| '{' '!' WRITE ':' WORD '}' { $$ = translator->block_expr_write(true, $5); }
| VALUE_EXPR { $$ = translator->block_expr_value($1); }
;

RAW_STRINGS: RAW_STRING { $$ = translator->raw_strings($1); }
| RAW_STRINGS RAW_STRING { $$ = translator->raw_strings($1, $2); }
;

IF_BODY: IF_BODY_EXPR { $$ = translator->if_body($1); }
| IF_BODY IF_BODY_EXPR { $$ = translator->if_body($1, $2); }
;

IF_BODY_EXPR: BLOCK_EXPR { $$ = translator->if_body_expr_block($1); }
| '{' ELSEIF ':' CONDITIONS '}' { $$ = translator->if_body_expr_elseif(false, $4); }
| '{' '!' ELSEIF ':' CONDITIONS '}' { $$ = translator->if_body_expr_elseif(true, $5); }
| '{' ELSE '}' { $$ = translator->if_body_expr_else(); }
;

FOR_ARGS: ARG { $$ = translator->for_args($1); }
| ARG ARG_SEP ARG { $$ = translator->for_args($1, $3); }
| ARG ARG_SEP ARG ARG_SEP ARG { $$ = translator->for_args($1, $3, $5); }
;

VALUE_EXPR: '{' VALUE_EXPR_BODY '}' { $$ = translator->value_expr(false, $2); }
| '{' '!' VALUE_EXPR_BODY '}' { $$ = translator->value_expr(true, $3); }
;

VALUE_EXPR_BODY: VIS ':' WORD MORE_NAMED_ARGS VIS_TAIL { $$ = translator->value_expr_body_vis($3, $4, $5); }
| WORD { $$ = translator->value_expr_body_variable($1); } 
| WORD ':' ARGS { $$ = translator->value_expr_body_call($1, $3); }
| LANG_WORD MORE_ARGS { $$ = translator->value_expr_body_lang($1, $2); } 
;

VIS_TAIL: /* empty */ { $$ = translator->vis_tail_none(); }
| REST_ARGS { $$ = translator->vis_tail_rest(); }
;

ARG_SEP: '|' | ',';

ARGS: ARG { $$ = translator->args($1); }
| ARGS ARG_SEP ARG { $$ = translator->args($1, $3); }
;

MORE_ARGS: /* empty */ { $$ = translator->args(); }
| ARG_SEP ARGS { $$ = $2; }
;

ARG: VALUE_EXPR { $$ = translator->arg_value($1); }
| WORD { $$ = translator->arg_variable($1); }
| STRING { $$ = translator->arg_raw($1); }
| NUMBER { $$ = translator->arg_numeric($1); }
;

NAMED_ARGS: NAMED_ARG { $$ = translator->named_args($1); }
| NAMED_ARGS ARG_SEP NAMED_ARG { $$ = translator->named_args($1, $3); }
;

NAMED_ARG: WORD '=' ARG { $$ = translator->named_arg($1, $3); }
;

MORE_NAMED_ARGS: /* empty */ { $$ = translator->named_args(); }
| ARG_SEP NAMED_ARGS { $$ = $2; }
;

CONDITIONS: CONDITION { $$ = translator->conditions($1); }
| CONDITIONS ARG_SEP CONDITION { $$ = translator->conditions($1, $3); }
;

CONDITION: ARG { $$ = translator->condition($1); }
| ARG CONDITION_OPERATOR ARG { $$ = translator->condition($2, $1, $3); }
;

CONDITION_OPERATOR: '=' { $$ = translator->condition_operator("=="); }
| CMP_OP { $$ = translator->condition_operator($1); }
;

ASSIGNMENTS: ASSIGNMENT { $$ = translator->assignments($1); }
| ASSIGNMENT ARG_SEP ASSIGNMENTS { $$ = translator->assignments($1, $3); }
;

ASSIGNMENT: WORD '=' ARG { $$ = translator->assignment($1, $3); }
;

%%


