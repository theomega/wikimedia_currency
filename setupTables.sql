# vim:tw=80:ts=2:sw=2:colorcolumn=81:nosmartindent

CREATE TABLE exchange_rates (
  currency VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_bin PRIMARY KEY,
  rate DOUBLE
);

