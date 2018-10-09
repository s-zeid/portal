main := src/portal.php
out := index.php

all: ${out}

${out}: ${main} $(sort $(wildcard lib/*.php)) $(sort $(filter-out ${main},$(wildcard src/*.php)))
	@echo deps: $^
	$(foreach f,$^,php -l $(f);)
	printf '<?php' > $@
	for f in $(filter-out ${main},$^) ${main}; do \
	 cat $$f | sed -e '1s/<?php//g' | sed -e '$$s/?>//g' >> $@; \
	done
	printf '?>\n' >> $@
	sed -i -e 's!require(['"'"'"]\(\.\.\?//*\)\?[^.]\+\.php['"'"'"]);!!g' $@

.PHONY: clean
clean:
	rm -f ${main}
