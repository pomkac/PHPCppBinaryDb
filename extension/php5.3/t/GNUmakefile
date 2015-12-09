default: test clean

test:
	prove

clean_targets = $(wildcard *.cmp *.t.tst *.t.err.tst *.t.eta *.dat)

.PHONY: clean
clean: 
	$(if $(clean_targets), rm $(clean_targets))