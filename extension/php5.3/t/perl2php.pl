#!perl -p 
s/\{/array(/g;
s/\[/array(/g;
s/\}/)/g;
s/\]/)/g;
s/undef/NULL/g;
