<?php

namespace FQL\Sql\Token;

enum TokenType: string
{
    // Structural
    case PAREN_OPEN = 'PAREN_OPEN';
    case PAREN_CLOSE = 'PAREN_CLOSE';
    case COMMA = 'COMMA';
    case STAR = 'STAR';

    // Top-level statement keywords
    case KEYWORD_SELECT = 'KEYWORD_SELECT';
    case KEYWORD_FROM = 'KEYWORD_FROM';
    case KEYWORD_WHERE = 'KEYWORD_WHERE';
    case KEYWORD_GROUP = 'KEYWORD_GROUP';
    case KEYWORD_BY = 'KEYWORD_BY';
    case KEYWORD_HAVING = 'KEYWORD_HAVING';
    case KEYWORD_ORDER = 'KEYWORD_ORDER';
    case KEYWORD_LIMIT = 'KEYWORD_LIMIT';
    case KEYWORD_OFFSET = 'KEYWORD_OFFSET';
    case KEYWORD_UNION = 'KEYWORD_UNION';
    case KEYWORD_ALL = 'KEYWORD_ALL';
    case KEYWORD_INTO = 'KEYWORD_INTO';
    case KEYWORD_DESCRIBE = 'KEYWORD_DESCRIBE';
    case KEYWORD_EXPLAIN = 'KEYWORD_EXPLAIN';
    case KEYWORD_ANALYZE = 'KEYWORD_ANALYZE';
    case KEYWORD_DISTINCT = 'KEYWORD_DISTINCT';
    case KEYWORD_EXCLUDE = 'KEYWORD_EXCLUDE';

    // Join keywords
    case KEYWORD_INNER = 'KEYWORD_INNER';
    case KEYWORD_LEFT = 'KEYWORD_LEFT';
    case KEYWORD_RIGHT = 'KEYWORD_RIGHT';
    case KEYWORD_FULL = 'KEYWORD_FULL';
    case KEYWORD_OUTER = 'KEYWORD_OUTER';
    case KEYWORD_JOIN = 'KEYWORD_JOIN';
    case KEYWORD_ON = 'KEYWORD_ON';
    case KEYWORD_AS = 'KEYWORD_AS';
    case KEYWORD_ASC = 'KEYWORD_ASC';
    case KEYWORD_DESC = 'KEYWORD_DESC';

    // CASE / WHEN / THEN / ELSE / END
    case KEYWORD_CASE = 'KEYWORD_CASE';
    case KEYWORD_WHEN = 'KEYWORD_WHEN';
    case KEYWORD_THEN = 'KEYWORD_THEN';
    case KEYWORD_ELSE = 'KEYWORD_ELSE';
    case KEYWORD_END = 'KEYWORD_END';

    // Logical / comparison keywords
    case KEYWORD_AND = 'KEYWORD_AND';
    case KEYWORD_OR = 'KEYWORD_OR';
    case KEYWORD_XOR = 'KEYWORD_XOR';
    case KEYWORD_NOT = 'KEYWORD_NOT';
    case KEYWORD_IS = 'KEYWORD_IS';
    case KEYWORD_IN = 'KEYWORD_IN';
    case KEYWORD_LIKE = 'KEYWORD_LIKE';
    case KEYWORD_BETWEEN = 'KEYWORD_BETWEEN';
    case KEYWORD_REGEXP = 'KEYWORD_REGEXP';
    case KEYWORD_AGAINST = 'KEYWORD_AGAINST';

    // Identifiers & literals
    case IDENTIFIER = 'IDENTIFIER';
    case IDENTIFIER_QUOTED = 'IDENTIFIER_QUOTED';
    case FUNCTION_NAME = 'FUNCTION_NAME';
    case STRING_LITERAL = 'STRING_LITERAL';
    case NUMBER_LITERAL = 'NUMBER_LITERAL';
    case BOOLEAN_LITERAL = 'BOOLEAN_LITERAL';
    case NULL_LITERAL = 'NULL_LITERAL';

    // Operators
    case OP_EQ = 'OP_EQ';
    case OP_EQ_STRICT = 'OP_EQ_STRICT';
    case OP_NEQ = 'OP_NEQ';
    case OP_NEQ_STRICT = 'OP_NEQ_STRICT';
    case OP_LT = 'OP_LT';
    case OP_LTE = 'OP_LTE';
    case OP_GT = 'OP_GT';
    case OP_GTE = 'OP_GTE';
    case OP_PLUS = 'OP_PLUS';
    case OP_MINUS = 'OP_MINUS';
    case OP_SLASH = 'OP_SLASH';
    case OP_PERCENT = 'OP_PERCENT';

    // Source - whole `format(path[, args]).query` expression as a single token
    case FILE_QUERY = 'FILE_QUERY';

    // Trivia & terminators
    case WHITESPACE = 'WHITESPACE';
    case COMMENT_LINE = 'COMMENT_LINE';
    case COMMENT_BLOCK = 'COMMENT_BLOCK';
    case EOF = 'EOF';

    public function isTrivia(): bool
    {
        return match ($this) {
            self::WHITESPACE,
            self::COMMENT_LINE,
            self::COMMENT_BLOCK => true,
            default => false,
        };
    }

    public function isKeyword(): bool
    {
        return str_starts_with($this->value, 'KEYWORD_');
    }

    public function isLiteral(): bool
    {
        return match ($this) {
            self::STRING_LITERAL,
            self::NUMBER_LITERAL,
            self::BOOLEAN_LITERAL,
            self::NULL_LITERAL => true,
            default => false,
        };
    }

    public function isOperator(): bool
    {
        return str_starts_with($this->value, 'OP_');
    }
}
