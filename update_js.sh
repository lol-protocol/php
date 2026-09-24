#!/bin/bash
for letter in H I J M N P R S T U V; do
  file="animacion-${letter,,}.js"
  if [ -f "$file" ]; then
    # Check if the file contains 'toggleAnimation' function
    if grep -q "toggleAnimation" "$file"; then
      # Extract the animated element from the file
      element=$(grep -oP "document.querySelector\('[^']*'\)" "$file" | head -1 | sed "s/document.querySelector('\([^']*\)')/\1/")
      if [ -z "$element" ]; then
        # Try with double quotes
        element=$(grep -oP 'document.querySelector\("[^"]*"\)' "$file" | head -1 | sed 's/document.querySelector("\([^"]*\)")/\1/')
      fi
      if [ -n "$element" ]; then
        echo "const toggle = new AnimationToggle('$element');" > "${file}.tmp"
        mv "${file}.tmp" "$file"
        echo "Updated JS for ${letter}"
      fi
    fi
  fi
done
