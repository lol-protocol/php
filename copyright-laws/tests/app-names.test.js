// Every name that app/**/*.js uses is declared, imported or a known global. The browser tests only catch a
// missing import if they happen to run that line; this reads the code instead, so it also covers the rest.
// (It was written after moving shared code into app/lib/view.js: two names were left unimported and only
// the end-to-end tests noticed.) This file is identical in privacy-laws/ and copyright-laws/
// (leyes-comunes/comprobar-copias.js keeps it that way).
import assert from "node:assert/strict";
import { readdirSync, readFileSync, statSync } from "node:fs";
import { dirname, join, relative } from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";
import { parse } from "acorn";

const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..");
const APP = join(ROOT, "app");

// What a browser module can use without importing it, beyond what Node already has in globalThis.
const BROWSER_GLOBALS = new Set([
  "document", "window", "location", "navigator", "history", "matchMedia", "alert", "confirm", "localStorage",
  "sessionStorage", "requestAnimationFrame", "cancelAnimationFrame", "getComputedStyle", "HTMLElement",
  "Element", "Node", "DOMParser", "CSS", "ResizeObserver", "IntersectionObserver", "MutationObserver",
  "Option", "requestIdleCallback", "scrollTo", "innerWidth", "innerHeight",
]);

function jsFiles(dir) {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) return jsFiles(path);
    return path.endsWith(".js") ? [path] : [];
  });
}

/** Names bound by a pattern (identifier, destructuring, default, rest). */
function patternNames(node, out = []) {
  if (!node) return out;
  switch (node.type) {
    case "Identifier": out.push(node.name); break;
    case "ObjectPattern": for (const p of node.properties) patternNames(p.type === "RestElement" ? p.argument : p.value, out); break;
    case "ArrayPattern": for (const e of node.elements) patternNames(e, out); break;
    case "AssignmentPattern": patternNames(node.left, out); break;
    case "RestElement": patternNames(node.argument, out); break;
    default: break;
  }
  return out;
}

/** Declarations that belong to this scope: its own let/const/class/function/import, plus var of the function. */
function declare(scope, statements, { isFunctionBody }) {
  const visitVar = (node) => {
    if (!node || typeof node.type !== "string") return;
    if (/Function/.test(node.type)) return; // another function has its own vars
    if (node.type === "VariableDeclaration" && node.kind === "var") for (const d of node.declarations) patternNames(d.id).forEach((n) => scope.add(n));
    for (const key of Object.keys(node)) {
      const child = node[key];
      if (Array.isArray(child)) child.forEach(visitVar);
      else if (child && typeof child.type === "string") visitVar(child);
    }
  };
  for (const s of statements) {
    if (s.type === "VariableDeclaration" && s.kind !== "var") for (const d of s.declarations) patternNames(d.id).forEach((n) => scope.add(n));
    else if (s.type === "FunctionDeclaration" || s.type === "ClassDeclaration") scope.add(s.id.name);
    else if (s.type === "ImportDeclaration") for (const sp of s.specifiers) scope.add(sp.local.name);
    else if ((s.type === "ExportNamedDeclaration" || s.type === "ExportDefaultDeclaration") && s.declaration) declare(scope, [s.declaration], { isFunctionBody: false });
  }
  if (isFunctionBody) statements.forEach(visitVar);
}

/** Names that are used but declared nowhere. */
export function undefinedNames(source) {
  const ast = parse(source, { ecmaVersion: "latest", sourceType: "module", allowHashBang: true });
  const missing = new Set();
  const isDeclared = (scopes, name) => scopes.some((s) => s.has(name)) || name in globalThis || BROWSER_GLOBALS.has(name) || name === "undefined" || name === "arguments";

  const walk = (node, scopes) => {
    if (!node || typeof node.type !== "string") return;
    const visitAll = (list, sc = scopes) => list.forEach((n) => walk(n, sc));
    switch (node.type) {
      case "Program": {
        const scope = new Set();
        declare(scope, node.body, { isFunctionBody: true });
        visitAll(node.body, [...scopes, scope]);
        return;
      }
      case "ImportDeclaration":
      case "ImportSpecifier":
      case "ExportAllDeclaration":
        return;
      case "ExportNamedDeclaration":
        if (node.declaration) walk(node.declaration, scopes);
        else for (const sp of node.specifiers) if (!node.source) walk(sp.local, scopes);
        return;
      case "FunctionDeclaration":
      case "FunctionExpression":
      case "ArrowFunctionExpression": {
        const scope = new Set(node.params.flatMap((p) => patternNames(p)));
        if (node.type === "FunctionExpression" && node.id) scope.add(node.id.name);
        const inner = [...scopes, scope];
        node.params.forEach((p) => { if (p.type === "AssignmentPattern") walk(p.right, inner); if (p.type !== "Identifier") walkPatternDefaults(p, inner); });
        if (node.body.type === "BlockStatement") {
          declare(scope, node.body.body, { isFunctionBody: true });
          visitAll(node.body.body, inner);
        } else walk(node.body, inner);
        return;
      }
      case "BlockStatement":
      case "StaticBlock": {
        const scope = new Set();
        declare(scope, node.body, { isFunctionBody: false });
        visitAll(node.body, [...scopes, scope]);
        return;
      }
      case "ForStatement": case "ForInStatement": case "ForOfStatement": {
        const scope = new Set();
        const head = node.type === "ForStatement" ? node.init : node.left;
        if (head && head.type === "VariableDeclaration") for (const d of head.declarations) patternNames(d.id).forEach((n) => scope.add(n));
        const inner = [...scopes, scope];
        for (const key of ["init", "test", "update", "left", "right", "body"]) {
          const child = node[key];
          if (!child) continue;
          if (child.type === "VariableDeclaration") child.declarations.forEach((d) => { walkPatternDefaults(d.id, inner); walk(d.init, inner); });
          else walk(child, inner);
        }
        return;
      }
      case "CatchClause": {
        const scope = new Set(patternNames(node.param));
        walk(node.body, [...scopes, scope]);
        return;
      }
      case "SwitchStatement": {
        walk(node.discriminant, scopes);
        const scope = new Set();
        declare(scope, node.cases.flatMap((c) => c.consequent), { isFunctionBody: false });
        node.cases.forEach((c) => { walk(c.test, [...scopes, scope]); visitAll(c.consequent, [...scopes, scope]); });
        return;
      }
      case "ClassDeclaration": case "ClassExpression": {
        const scope = new Set(node.id ? [node.id.name] : []);
        const inner = [...scopes, scope];
        walk(node.superClass, inner);
        node.body.body.forEach((m) => { if (m.computed) walk(m.key, inner); walk(m.value, inner); if (m.type === "StaticBlock") walk(m, inner); });
        return;
      }
      case "VariableDeclaration":
        node.declarations.forEach((d) => { walkPatternDefaults(d.id, scopes); walk(d.init, scopes); });
        return;
      case "MemberExpression":
        walk(node.object, scopes);
        if (node.computed) walk(node.property, scopes);
        return;
      case "Property":
        if (node.computed) walk(node.key, scopes);
        walk(node.value, scopes);
        return;
      case "LabeledStatement": walk(node.body, scopes); return;
      case "BreakStatement": case "ContinueStatement": return;
      case "MetaProperty": return;
      case "Identifier":
        if (!isDeclared(scopes, node.name)) missing.add(node.name);
        return;
      default:
        for (const key of Object.keys(node)) {
          const child = node[key];
          if (Array.isArray(child)) child.forEach((c) => walk(c, scopes));
          else if (child && typeof child.type === "string") walk(child, scopes);
        }
    }
  };

  /** Default values inside a binding pattern are expressions that use names. */
  function walkPatternDefaults(pattern, scopes) {
    if (!pattern) return;
    if (pattern.type === "AssignmentPattern") { walk(pattern.right, scopes); walkPatternDefaults(pattern.left, scopes); }
    else if (pattern.type === "ObjectPattern") pattern.properties.forEach((p) => { if (p.computed) walk(p.key, scopes); walkPatternDefaults(p.type === "RestElement" ? p.argument : p.value, scopes); });
    else if (pattern.type === "ArrayPattern") pattern.elements.forEach((e) => walkPatternDefaults(e, scopes));
    else if (pattern.type === "RestElement") walkPatternDefaults(pattern.argument, scopes);
  }

  walk(ast, []);
  return [...missing].sort();
}

test("the checker itself notices an undeclared name and ignores the declared ones", () => {
  const src = `import { a } from "./x.js";
    const { b, c = a } = {};
    function f(d, { e } = {}) { var g = 1; for (const h of [d]) { g += h + e + b; } try {} catch (err) { return err; } return g + notDeclared; }
    const o = { key: a, [b]: c, shorthand: 1 }; o.prop; label: for (;;) { break label; }
    class K extends Object { static s = 1; m() { return this.s + missingInClass; } }
    export const z = (x) => x + document.title + Math.max(1) + y;`;
  assert.deepEqual(undefinedNames(src), ["missingInClass", "notDeclared", "y"]);
});

test("every name the page's modules use is declared, imported or a known global", () => {
  const problems = [];
  for (const file of jsFiles(APP)) {
    for (const name of undefinedNames(readFileSync(file, "utf8"))) problems.push(`${relative(ROOT, file)}: ${name}`);
  }
  assert.deepEqual(problems, []);
});
