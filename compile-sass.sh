#!/bin/bash
 
# Executa o comando 'sass' para verificar se existe (veja http://stackoverflow.com/a/677212/329911)
command -v sass >/dev/null 2>&1 || {
  echo >&2 "SASS parece não está disponivel.";
  exit 1;
}
 
# Define o caminho.
echo "Compilando Sass..."
cd public/wp-content/themes/rhs/assets/scss/
 
sass -E 'UTF-8' style.scss:../../style.css
sass -E 'UTF-8' rhs_editor_style.scss:../../rhs_editor_style.css
echo "Sass Compilado"
 
echo "Compilação do Sass Concluído!"
exit 0
