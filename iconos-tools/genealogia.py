#!/usr/bin/env python3
"""Catalogo abstracto de genealogia: diagramas de parentesco sin texto.

Gramatica visual: naranja = el familiar, azul oscuro con punto = tu, gris claro = otros;
cuadrado = hombre, circulo = mujer, rombo = sin especificar; linea doble = matrimonio,
linea cortada = divorcio, discontinua = adopcion o parentesco parcial, punteada = acogida.
"""
import math

from glyphs import GLYPHS
from lib import Block, Disc, Icon, RPoly, fmt

def arc_pts(cx, cy, r, a0, a1, n=48):
    return [(cx + r * math.cos(math.radians(a0 + (a1 - a0) * i / n)),
             cy + r * math.sin(math.radians(a0 + (a1 - a0) * i / n))) for i in range(n + 1)]


def bezier_pts(p0, p1, p2, p3, n=40):
    out = []
    for i in range(n + 1):
        t = i / n
        u = 1 - t
        out.append((u ** 3 * p0[0] + 3 * u * u * t * p1[0] + 3 * u * t * t * p2[0] + t ** 3 * p3[0],
                    u ** 3 * p0[1] + 3 * u * u * t * p1[1] + 3 * u * t * t * p2[1] + t ** 3 * p3[1]))
    return out


ICONS = []
KIND = {'m': 'sq', 'f': 'ci', 'u': 'di'}
CHAIN = {1: dict(r=7.4, h=34), 2: dict(r=6.4, h=40), 3: dict(r=5.6, h=42)}
SMALL = {3: 3.3}
ROLE = {'self': 'ink', 'rel': 'orange', 'other': 'mist', 'rel2': 'amber'}


def reg(ic):
    ICONS.append(ic)
    return ic


def g(name):
    return GLYPHS[name]


# ------------------------------------------------------------------ nodos
def node(ic, kind, x, y, role='other', r=6.0, d=None):
    d = max(1.8, min(3.2, 0.42 * r)) if d is None else d
    if kind == 'sq':
        shape = Block(x - r, y - r, 2 * r, 2 * r, r=r * 0.3)
    elif kind == 'ci':
        shape = Disc(x, y, r * 1.1)
    else:
        s = r * 1.46
        shape = Block(x - s / 2, y - s / 2, s, s, r=r * 0.22, rot=45)
    ic.solid(shape, ROLE[role], d=d)
    if role == 'self':
        ic.dot(x, y, max(1.6, r * 0.34))


class Scene:
    """Diagrama de parentesco: nodos en una rejilla (col, fila) y enlaces entre ellos."""

    def __init__(self, stem, r=None, w=40.0, h=40.0):
        self.stem, self.r, self.w, self.h = stem, r, w, h
        self.nodes, self.edges, self.decor = {}, [], []

    def n(self, name, kind, col, row, role='other', size=None):
        self.nodes[name] = dict(kind=KIND.get(kind, kind), col=col, row=row, role=role, size=size)
        return self

    def couple(self, a, b, style='double'):
        self.edges.append(('couple', a, b, style))
        return self

    def desc(self, parents, child, style='line', mark=None):
        self.edges.append(('desc', parents, child, style, mark))
        return self

    def line(self, a, b, style='line'):
        self.edges.append(('line', a, b, style))
        return self

    def build(self):
        ic = Icon(self.stem)
        cols = [v['col'] for v in self.nodes.values()]
        rows = [v['row'] for v in self.nodes.values()]
        cmin, cmax, rmin, rmax = min(cols), max(cols), min(rows), max(rows)
        span_c, span_r = cmax - cmin, rmax - rmin
        sx = min(28.0, self.w / span_c) if span_c else 0.0
        sy = min(34.0, self.h / span_r) if span_r else 0.0
        cands = [8.0]
        if span_c:
            cands.append(0.34 * sx)
        if span_r:
            cands.append(0.34 * sy)
        r = self.r or max(4.4, min(cands))
        x0, y0 = 31 - span_c * sx / 2, 30.5 - span_r * sy / 2
        pos = {k: (x0 + (v['col'] - cmin) * sx, y0 + (v['row'] - rmin) * sy) for k, v in self.nodes.items()}

        def at(ref):
            if isinstance(ref, tuple):
                (ax, ay), (bx, by) = pos[ref[0]], pos[ref[1]]
                return ((ax + bx) / 2, (ay + by) / 2)
            return pos[ref]

        marks = []
        for e in self.edges:
            if e[0] == 'couple':
                ic.link([pos[e[1]], pos[e[2]]], e[3])
            elif e[0] == 'line':
                ic.link([at(e[1]), at(e[2])], e[3])
            else:
                _, parents, child, style, mark = e
                ps, pc = at(parents), pos[child]
                if abs(ps[0] - pc[0]) < 0.5:
                    pts = [ps, pc]
                else:
                    ym = ps[1] + 0.5 * (pc[1] - ps[1])
                    pts = [ps, (ps[0], ym), (pc[0], ym), pc]
                ic.link(pts, style)
                if mark:
                    marks.append((mark, pts, pc, ps))
        for k, v in self.nodes.items():
            node(ic, v['kind'], pos[k][0], pos[k][1], v['role'], r=v['size'] or r)
        for mark, pts, pc, ps in marks:
            # marca sobre el tramo vertical final, entre el nudo y el hijo
            mx, my = pc[0], (pc[1] + (pts[-2][1] if len(pts) > 2 else ps[1])) / 2
            if mark == 'ring':
                ic.ring(mx, my, 3.6, tone='orange', ink='orange', w=2.4)
            elif mark == 'arrow':
                ic.poly([(mx - 3.4, my - 2), (mx, my + 2.2), (mx + 3.4, my - 2)], tone='orange', ink='orange', w=2.6)
            elif mark == 'heart':
                k = 0.5
                ic.mark(f'M{fmt(mx)} {fmt(my + 8 * k)}C{fmt(mx - 12 * k)} {fmt(my - k)} {fmt(mx - 6 * k)} {fmt(my - 10 * k)} {fmt(mx)} {fmt(my - 4 * k)}'
                        f'C{fmt(mx + 6 * k)} {fmt(my - 10 * k)} {fmt(mx + 12 * k)} {fmt(my - k)} {fmt(mx)} {fmt(my + 8 * k)}Z',
                        filled=True, tone='rose', ink='rose')
            elif mark == 'square':
                ic.poly([(mx - 3.2, my - 3.2), (mx + 3.2, my - 3.2), (mx + 3.2, my + 3.2), (mx - 3.2, my + 3.2)], True,
                        tone='orange', ink='orange', w=2.2)
        for fn in self.decor:
            fn(ic, pos, r)
        return ic


def scene(stem, **kw):
    return Scene(stem, **kw)


def done(sc):
    return reg(sc.build())


# ------------------------------------------------------------------ person_: lineas directas
def ancestor(stem, k, gender, side=None):
    if side is None:
        s = scene(stem, **CHAIN[k])
        s.n('rel', gender, 0, 0, 'rel')
        for i in range(1, k):
            s.n(f'm{i}', 'u', 0, i, size=SMALL.get(k))
        s.n('me', 'f', 0, k, 'self')
        chain = ['rel'] + [f'm{i}' for i in range(1, k)] + ['me']
        for a_, b_ in zip(chain, chain[1:]):
            s.desc(a_, b_)
    else:
        s = scene(stem)
        s.n('f', 'm', 0, 1).n('m', 'f', 2, 1).n('me', 'f', 1, 2, 'self')
        s.couple('f', 'm').desc(('f', 'm'), 'me')
        s.n('rel', gender, 0 if side == 'p' else 2, 0, 'rel').desc('rel', 'f' if side == 'p' else 'm')
    return done(s)


def descendant(stem, k, gender):
    s = scene(stem, **CHAIN[k])
    s.n('me', 'f', 0, 0, 'self')
    prev = 'me'
    for i in range(1, k):
        s.n(f'm{i}', 'u', 0, i, size=SMALL.get(k)).desc(prev, f'm{i}')
        prev = f'm{i}'
    s.n('rel', gender, 0, k, 'rel').desc(prev, 'rel')
    return done(s)


def collateral(stem, u, d, gender):
    s = scene(stem)
    s.n('a', 'u', 0.5, 0)
    prev = 'a'
    for i in range(1, u):
        s.n(f'l{i}', 'u', 0, i).desc(prev, f'l{i}')
        prev = f'l{i}'
    s.n('me', 'f', 0, u, 'self').desc(prev, 'me')
    prev = 'a'
    for i in range(1, d):
        s.n(f'r{i}', 'u', 1, i).desc(prev, f'r{i}')
        prev = f'r{i}'
    s.n('rel', gender, 1, d, 'rel').desc(prev, 'rel')
    return done(s)


for suffix, gender in (('father', 'm'), ('mother', 'f'), ('parent', 'u')):
    ancestor(f'person_{suffix}', 1, gender)
for suffix, gender in (('grandfather', 'm'), ('grandmother', 'f'), ('grandparent', 'u')):
    ancestor(f'person_{suffix}', 2, gender)
ancestor('person_grandfather_paternal', 2, 'm', 'p')
ancestor('person_grandmother_paternal', 2, 'f', 'p')
ancestor('person_grandfather_maternal', 2, 'm', 'm')
ancestor('person_grandmother_maternal', 2, 'f', 'm')
for suffix, gender in (('great_grandfather', 'm'), ('great_grandmother', 'f'), ('great_grandparent', 'u')):
    ancestor(f'person_{suffix}', 3, gender)

for suffix, gender in (('son', 'm'), ('daughter', 'f'), ('child', 'u')):
    descendant(f'person_{suffix}', 1, gender)
for suffix, gender in (('grandson', 'm'), ('granddaughter', 'f'), ('grandchild', 'u')):
    descendant(f'person_{suffix}', 2, gender)
for suffix, gender in (('great_grandson', 'm'), ('great_granddaughter', 'f'), ('great_grandchild', 'u')):
    descendant(f'person_{suffix}', 3, gender)

# hermanos
for suffix, gender in (('brother', 'm'), ('sister', 'f'), ('sibling', 'u')):
    collateral(f'person_{suffix}', 1, 1, gender)
for suffix, gender in (('half_brother', 'm'), ('half_sister', 'f')):
    s = scene(f'person_{suffix}')
    s.n('a', 'u', 1, 0).n('me', 'f', 0, 1, 'self').n('rel', gender, 2, 1, 'rel')
    s.desc('a', 'me').desc('a', 'rel', 'dashed')
    done(s)

# colaterales
for suffix, u, d, gender in (
    ('uncle', 2, 1, 'm'), ('aunt', 2, 1, 'f'), ('great_uncle', 3, 1, 'm'), ('great_aunt', 3, 1, 'f'),
    ('nephew', 1, 2, 'm'), ('niece', 1, 2, 'f'), ('great_nephew', 1, 3, 'm'), ('great_niece', 1, 3, 'f'),
    ('cousin', 2, 2, 'm'), ('cousin_f', 2, 2, 'f'), ('second_cousin', 3, 3, 'm'), ('second_cousin_f', 3, 3, 'f'),
    ('cousin_once_removed', 2, 3, 'm'), ('cousin_once_removed_f', 2, 3, 'f'),
):
    collateral(f'person_{suffix}', u, d, gender)

# ------------------------------------------------------------------ person_: politicos
for suffix, gender in (('stepfather', 'm'), ('stepmother', 'f')):
    s = scene(f'person_{suffix}')
    s.n('p', 'f' if gender == 'm' else 'm', 0, 0).n('rel', gender, 2, 0, 'rel').n('me', 'f', 0, 1, 'self')
    s.couple('p', 'rel').desc('p', 'me')
    done(s)
for suffix, gender in (('stepson', 'm'), ('stepdaughter', 'f')):
    s = scene(f'person_{suffix}')
    s.n('me', 'f', 0, 0, 'self').n('p', 'u', 2, 0).n('rel', gender, 2, 1, 'rel')
    s.couple('me', 'p').desc('p', 'rel')
    done(s)
for suffix, gender in (('stepbrother', 'm'), ('stepsister', 'f')):
    s = scene(f'person_{suffix}')
    s.n('p1', 'u', 0, 0).n('p2', 'u', 2, 0).n('me', 'f', 0, 1, 'self').n('rel', gender, 2, 1, 'rel')
    s.couple('p1', 'p2').desc('p1', 'me').desc('p2', 'rel')
    done(s)

# adopcion, acogida, padrinos, tutela
for stem, gender, up, style, mark in (
    ('person_adoptive_father', 'm', True, 'dashed', 'heart'), ('person_adoptive_mother', 'f', True, 'dashed', 'heart'),
    ('person_adopted_son', 'm', False, 'dashed', 'heart'), ('person_adopted_daughter', 'f', False, 'dashed', 'heart'),
    ('person_foster_father', 'm', True, 'dotted', 'square'), ('person_foster_mother', 'f', True, 'dotted', 'square'),
    ('person_foster_son', 'm', False, 'dotted', 'square'), ('person_foster_daughter', 'f', False, 'dotted', 'square'),
    ('person_godfather', 'm', True, 'line', 'ring'), ('person_godmother', 'f', True, 'line', 'ring'),
    ('person_godson', 'm', False, 'line', 'ring'), ('person_goddaughter', 'f', False, 'line', 'ring'),
    ('person_guardian', 'u', True, 'line', 'arrow'), ('person_ward', 'u', False, 'line', 'arrow'),
):
    s = scene(stem, r=6.8, h=36)
    if up:
        s.n('rel', gender, 0, 0, 'rel').n('me', 'f', 0, 1, 'self').desc('rel', 'me', style, mark)
    else:
        s.n('me', 'f', 0, 0, 'self').n('rel', gender, 0, 1, 'rel').desc('me', 'rel', style, mark)
    done(s)

# parentesco politico
for suffix, gender in (('father_in_law', 'm'), ('mother_in_law', 'f')):
    s = scene(f'person_{suffix}')
    s.n('rel', gender, 1, 0, 'rel').n('sp', 'u', 1, 1).n('me', 'f', 0, 1, 'self')
    s.couple('me', 'sp').desc('rel', 'sp')
    done(s)
for suffix, gender in (('son_in_law', 'm'), ('daughter_in_law', 'f')):
    s = scene(f'person_{suffix}')
    s.n('me', 'f', 0, 0, 'self').n('ch', 'u', 0, 1).n('rel', gender, 1, 1, 'rel')
    s.couple('ch', 'rel').desc('me', 'ch')
    done(s)
for suffix, gender in (('brother_in_law', 'm'), ('sister_in_law', 'f')):
    s = scene(f'person_{suffix}')
    s.n('p', 'u', 1.5, 0).n('me', 'f', 0, 1, 'self').n('sp', 'u', 1, 1).n('rel', gender, 2, 1, 'rel')
    s.couple('me', 'sp').desc('p', 'sp').desc('p', 'rel')
    done(s)
for suffix, gender in (('brother_in_law_via_sibling', 'm'), ('sister_in_law_via_sibling', 'f')):
    s = scene(f'person_{suffix}')
    s.n('p', 'u', 0.5, 0).n('me', 'f', 0, 1, 'self').n('sib', 'u', 1, 1).n('rel', gender, 2, 1, 'rel')
    s.couple('sib', 'rel').desc('p', 'me').desc('p', 'sib')
    done(s)

# pareja
for suffix, gender, style in (
    ('spouse', 'u', 'double'), ('husband', 'm', 'double'), ('wife', 'f', 'double'), ('partner', 'u', 'line'),
    ('ex_husband', 'm', 'cut'), ('ex_wife', 'f', 'cut'),
):
    s = scene(f'person_{suffix}')
    s.n('me', 'f', 0, 0, 'self').n('rel', gender, 1, 0, 'rel').couple('me', 'rel', style)
    if suffix in ('spouse', 'husband', 'wife'):
        s.decor.append(lambda ic, pos, r: (ic.ring(27.5, 14, 5.2, tone='orange', ink='orange', w=2.6),
                                           ic.ring(34.5, 14, 5.2, tone='orange', ink='orange', w=2.6)))
    done(s)

# uno mismo y desconocidos
ic = reg(Icon('person_self'))
node(ic, 'ci', 31, 30, 'self', r=11, d=4)
ic.ring(31, 30, 18, tone='slate', ink='slate', w=2, dash='3 3.6')

for stem, kind in (('person_unknown', 'di'), ('person_unknown_male', 'sq'), ('person_unknown_female', 'ci')):
    ic = reg(Icon(stem))
    node(ic, kind, 31, 30, 'other', r=9.5, d=3.4)
    ic.ring(31, 30, 19, tone='slate', ink='slate', w=2, dash='3 3.6')

# ------------------------------------------------------------------ relationship_
s = scene('relationship_married')
s.n('a', 'm', 0, 0).n('b', 'f', 1, 0).couple('a', 'b')
s.decor.append(lambda ic, pos, r: (ic.ring(27.5, 15, 5.6, tone='orange', ink='orange', w=2.8),
                                   ic.ring(34.5, 15, 5.6, tone='orange', ink='orange', w=2.8)))
done(s)

s = scene('relationship_dissolved')
s.n('a', 'm', 0, 0).n('b', 'f', 1, 0).couple('a', 'b', 'cut')
done(s)

s = scene('relationship_engaged')
s.n('a', 'm', 0, 0).n('b', 'f', 1, 0).couple('a', 'b', 'dotted')
s.decor.append(lambda ic, pos, r: (ic.ring(31, 18, 5.2, tone='orange', ink='orange', w=2.6),
                                   ic.dot(31, 11.6, 1.8, tone='orange', ink='orange')))
done(s)

s = scene('relationship_partnership')
s.n('a', 'u', 0, 0).n('b', 'u', 1, 0).couple('a', 'b', 'line')
done(s)

s = scene('relationship_romantic')
s.n('a', 'm', 0, 0, 'rel2').n('b', 'f', 1, 0, 'rel2').couple('a', 'b', 'line')
s.decor.append(lambda ic, pos, r: g('heart')(ic, 31, 17.5, tone='rose', ink='rose'))
done(s)

s = scene('relationship_twins')
s.n('p', 'u', 0.5, 0).n('a', 'u', 0, 1, 'rel').n('b', 'u', 1, 1, 'rel')
s.line('p', 'a').line('p', 'b').line('a', 'b', 'dashed')
done(s)

s = scene('relationship_triplets')
s.n('p', 'u', 1, 0).n('a', 'u', 0, 1, 'rel').n('b', 'u', 1, 1, 'rel').n('c', 'u', 2, 1, 'rel')
s.line('p', 'a').line('p', 'b').line('p', 'c')
done(s)

s = scene('relationship_ancestor')
s.n('a', 'u', 0, 0, 'rel').n('b', 'u', 0, 1, 'rel').n('me', 'f', 0, 2, 'self').desc('a', 'b').desc('b', 'me')
done(s)

s = scene('relationship_descendant')
s.n('me', 'f', 0, 0, 'self').n('a', 'u', 0, 1, 'rel').n('b', 'u', 0, 2, 'rel').desc('me', 'a').desc('a', 'b')
done(s)

s = scene('relationship_lineage_paternal')
s.n('a', 'm', 0, 0, 'rel').n('b', 'm', 0, 1, 'rel').n('c', 'm', 0, 2, 'rel').n('me', 'f', 0, 3, 'self')
s.desc('a', 'b').desc('b', 'c').desc('c', 'me')
done(s)

s = scene('relationship_lineage_maternal')
s.n('a', 'f', 0, 0, 'rel').n('b', 'f', 0, 1, 'rel').n('c', 'f', 0, 2, 'rel').n('me', 'f', 0, 3, 'self')
s.desc('a', 'b').desc('b', 'c').desc('c', 'me')
done(s)

ic = reg(Icon('relationship_blood_bond'))
ic.link([(15, 30), (47, 30)], w=4.6)
node(ic, 'di', 15, 30, 'rel', r=7.4, d=3)
node(ic, 'di', 47, 30, 'rel', r=7.4, d=3)
g('drop')(ic, 31, 30, tone='red', ink='red')

s = scene('relationship_adoption')
s.n('a', 'm', 0, 0).n('b', 'f', 2, 0).n('c', 'u', 1, 1, 'rel')
s.couple('a', 'b').desc(('a', 'b'), 'c', 'dashed', 'heart')
done(s)

s = scene('relationship_fostering')
s.n('a', 'm', 0, 0).n('b', 'f', 2, 0).n('c', 'u', 1, 1, 'rel')
s.couple('a', 'b').desc(('a', 'b'), 'c', 'dotted', 'square')
done(s)

s = scene('relationship_guardianship')
s.n('a', 'u', 0, 0, 'rel').n('b', 'u', 0, 1, 'rel2').desc('a', 'b', 'line', 'arrow')
done(s)

s = scene('relationship_godparenthood')
s.n('a', 'm', 0, 0).n('b', 'f', 2, 0).n('c', 'u', 1, 1, 'rel').couple('a', 'b', 'line').desc(('a', 'b'), 'c', 'line', 'ring')
done(s)

s = scene('relationship_half_siblings')
s.n('pa', 'f', 0, 0).n('ps', 'm', 1, 0).n('pb', 'f', 2, 0).n('ca', 'u', 0.5, 1, 'rel').n('cb', 'u', 1.5, 1, 'rel')
s.couple('pa', 'ps').couple('ps', 'pb').desc(('pa', 'ps'), 'ca').desc(('ps', 'pb'), 'cb')
done(s)

s = scene('relationship_step_family')
s.n('p1', 'u', 0, 0).n('p2', 'u', 2, 0).n('c1', 'u', 0, 1, 'rel').n('c2', 'u', 2, 1, 'rel')
s.couple('p1', 'p2').desc('p1', 'c1').desc('p2', 'c2').line('c1', 'c2', 'dashed')
done(s)

s = scene('relationship_nuclear_family')
s.n('a', 'm', 0, 0).n('b', 'f', 2, 0).n('c1', 'u', 0.5, 1, 'rel').n('c2', 'u', 1.5, 1, 'rel')
s.couple('a', 'b').desc(('a', 'b'), 'c1').desc(('a', 'b'), 'c2')
done(s)

s = scene('relationship_single_parent_family')
s.n('a', 'u', 1, 0).n('c1', 'u', 0, 1, 'rel').n('c2', 'u', 2, 1, 'rel').desc('a', 'c1').desc('a', 'c2')
done(s)

s = scene('relationship_extended_family')
s.n('a', 'm', 0.5, 0).n('b', 'f', 1.5, 0).n('c1', 'u', 0, 1).n('c2', 'u', 2, 1)
s.n('g1', 'u', -0.5, 2, 'rel').n('g2', 'u', 0.5, 2, 'rel').n('g3', 'u', 2, 2, 'rel')
s.couple('a', 'b').desc(('a', 'b'), 'c1').desc(('a', 'b'), 'c2')
s.desc('c1', 'g1').desc('c1', 'g2').desc('c2', 'g3')
done(s)

s = scene('relationship_family_branch')
s.n('a', 'u', 1, 0).n('b', 'u', 0, 1).n('c', 'u', 2, 1, 'rel').n('d', 'u', 1.5, 2, 'rel').n('e', 'u', 2.5, 2, 'rel')
s.desc('a', 'b').desc('a', 'c').desc('c', 'd').desc('c', 'e')
done(s)

s = scene('relationship_co_brother_in_law')
s.n('w1', 'f', 0, 0).n('h1', 'm', 1, 0, 'rel').n('h2', 'm', 1, 1, 'rel').n('w2', 'f', 2, 1)
s.couple('w1', 'h1').couple('h2', 'w2').line('h1', 'h2', 'dashed')
done(s)

s = scene('relationship_co_sister_in_law')
s.n('h1', 'm', 0, 0).n('w1', 'f', 1, 0, 'rel').n('w2', 'f', 1, 1, 'rel').n('h2', 'm', 2, 1)
s.couple('h1', 'w1').couple('w2', 'h2').line('w1', 'w2', 'dashed')
done(s)

ic = reg(Icon('relationship_family_reunion'))
for k in range(6):
    a = math.radians(-90 + 60 * k)
    x, y = 31 + 19 * math.cos(a), 30 + 17 * math.sin(a)
    ic.link([(31, 30), (x, y)], w=2)
for k, kind in enumerate(('sq', 'ci', 'di', 'sq', 'ci', 'di')):
    a = math.radians(-90 + 60 * k)
    node(ic, kind, 31 + 19 * math.cos(a), 30 + 17 * math.sin(a), 'other', r=4.8, d=2)
node(ic, 'ci', 31, 30, 'rel', r=6, d=2.6)

ic = reg(Icon('relationship_generational_arc'))
ic.link(bezier_pts((12, 48), (16, 22), (36, 12), (52, 14)), 'dotted', w=2.4)
for (x, y, r), role in zip(((13, 47, 5.6), (22, 28, 5.0), (36, 18, 4.5), (51, 14, 4.0)), ('self', 'other', 'other', 'rel')):
    node(ic, 'ci' if role == 'self' else 'di', x, y, role, r=r, d=2)

ic = reg(Icon('relationship_inheritance_division'))
node(ic, 'di', 31, 12, 'rel', r=6.2, d=2.6)
for x in (13, 31, 49):
    ic.link([(31, 12), (31, 24), (x, 24), (x, 38)], w=2.2)
    ic.arrow(x, 34, x, 40, head=3.6, tone='orange', ink='orange', w=2.4)
for x in (13, 31, 49):
    node(ic, 'ci', x, 47, 'other', r=5.0, d=2.2)

# ------------------------------------------------------------------ state_
def solo(stem, deco=None, role='self', kind='ci', x=31, y=33):
    ic = Icon(stem)
    node(ic, kind, x, y, role, r=9.5, d=3.4)
    if deco:
        deco(ic)
    return reg(ic)


solo('state_single', lambda ic: ic.ring(31, 33, 18, tone='slate', ink='slate', w=2, dash='3 3.6'))
solo('state_married', lambda ic: (ic.ring(27.5, 12, 5.2, tone='orange', ink='orange', w=2.6),
                                 ic.ring(34.5, 12, 5.2, tone='orange', ink='orange', w=2.6)))
solo('state_engaged', lambda ic: (ic.ring(31, 14, 4.8, tone='orange', ink='orange', w=2.6),
                                 ic.dot(31, 8.6, 1.7, tone='orange', ink='orange')))
solo('state_divorced', lambda ic: (ic.arc(27.5, 12, 5.2, 120, 400, tone='orange', ink='orange', w=2.6),
                                  ic.arc(35.5, 12, 5.2, 300, 580, tone='orange', ink='orange', w=2.6)))
solo('state_separated', lambda ic: (ic.ring(21, 12, 4.8, tone='orange', ink='orange', w=2.6),
                                   ic.ring(41, 12, 4.8, tone='orange', ink='orange', w=2.6),
                                   ic.line(26.8, 12, 35.2, 12, tone='orange', ink='orange', w=2.2, dash='0.1 3.6')))
solo('state_deceased', lambda ic: ic.line(15, 49, 47, 17, w=3, tone='night', ink='night'))
solo('state_living', lambda ic: (ic.dot(47, 15, 7, tone='green', ink='green'), ic.check(47, 15, 3.6, w=2.2, tone='white')))

ic = reg(Icon('state_widowed'))
ic.link([(15, 31), (47, 31)], 'double')
node(ic, 'ci', 15, 31, 'other', r=7.4, d=3)
ic.line(8, 43, 24, 19, w=2.8, tone='night', ink='night')
node(ic, 'ci', 47, 31, 'self', r=7.4, d=3)

# ------------------------------------------------------------------ view_
def tree_icon(stem, ys, levels, radii, kinds=None, role0='self'):
    ic = Icon(stem)
    for i in range(1, len(levels)):
        for x in levels[i]:
            parent = min(levels[i - 1], key=lambda px: abs(px - x))
            ym = (ys[i - 1] + ys[i]) / 2
            ic.link([(parent, ys[i - 1]), (parent, ym), (x, ym), (x, ys[i])], w=2.2)
    for i, lvl in enumerate(levels):
        for j, x in enumerate(lvl):
            node(ic, kinds[i][j] if kinds else 'ci', x, ys[i], role0 if i == 0 else 'other', r=radii[i], d=2)
    return reg(ic)


ic = reg(Icon('view_tree'))
ic.link([(31, 12), (31, 21)])
ic.link([(17, 21), (45, 21)])
ic.link([(17, 21), (17, 30)])
ic.link([(45, 21), (45, 30)])
for cx, bx in ((17, (10, 24)), (45, (38, 52))):
    ic.link([(cx, 30), (cx, 40)])
    ic.link([(bx[0], 40), (bx[1], 40)])
    ic.link([(bx[0], 40), (bx[0], 49)])
    ic.link([(bx[1], 40), (bx[1], 49)])
node(ic, 'ci', 31, 12, 'rel', r=4.6, d=2.2)
for x in (17, 45):
    node(ic, 'ci', x, 30, 'other', r=4.2, d=2)
for x in (10, 24, 38, 52):
    node(ic, 'ci', x, 49, 'other', r=3.6, d=1.8)

tree_icon('view_descendants', (12, 30, 48), [[31], [12, 31, 50], [7, 17, 50]], [5.2, 4.6, 3.8])
tree_icon('view_ancestors', (50, 32, 14), [[31], [17, 45], [10, 24, 38, 52]], [5.2, 4.6, 3.8], kinds=[['ci'], ['di', 'di'], ['di'] * 4])

ic = reg(Icon('view_hourglass'))
for x in (19, 43):
    ic.link([(x, 12), (x, 20), (31, 20), (31, 30)], w=2.2)
    ic.link([(31, 30), (31, 40), (x, 40), (x, 48)], w=2.2)
for x in (19, 43):
    node(ic, 'di', x, 12, 'other', r=4.8, d=2)
    node(ic, 'di', x, 48, 'other', r=4.8, d=2)
node(ic, 'ci', 31, 30, 'self', r=6, d=2.6)

tree_icon('view_pedigree', (50, 32, 14), [[31], [17, 45], [10, 24, 38, 52]], [5.2, 4.6, 3.8],
          kinds=[['ci'], ['sq', 'ci'], ['sq', 'ci', 'sq', 'ci']])

ic = reg(Icon('view_fan'))
for rad in (10, 19, 28):
    ic.link(arc_pts(31, 50, rad, 200, 340), w=2.2)
ic.link([(31, 50), (31, 22)], w=2)
ic.link([(31, 50), (13, 29)], w=2)
ic.link([(31, 50), (49, 29)], w=2)
node(ic, 'ci', 31, 50, 'self', r=5.0, d=2)
for x, y in ((31, 22), (13, 29), (49, 29)):
    node(ic, 'di', x, y, 'other', r=3.8, d=1.8)
for x, y in ((21, 40), (41, 40)):
    node(ic, 'di', x, y, 'rel', r=3.4, d=1.6)

ic = reg(Icon('view_circular'))
ic.link(arc_pts(31, 30, 11, 0, 360), w=2)
ic.link(arc_pts(31, 30, 21, 0, 360), w=2)
for k in range(4):
    a = math.radians(45 + 90 * k)
    node(ic, 'ci', 31 + 11 * math.cos(a), 30 + 11 * math.sin(a), 'other', r=3.6, d=1.6)
for k in range(8):
    a = math.radians(22.5 + 45 * k)
    node(ic, 'ci', 31 + 21 * math.cos(a), 30 + 21 * math.sin(a), 'other', r=3.0, d=1.4)
node(ic, 'ci', 31, 30, 'rel', r=4.4, d=2)

ic = reg(Icon('view_generations'))
for y, xs in ((14, [31]), (31, [21, 41]), (48, [14, 31, 48])):
    ic.solid(Block(8, y - 6, 48, 12, r=6), 'mist', d=0, op=0.55)
    for x in xs:
        node(ic, 'ci', x, y, 'rel' if y == 14 else 'other', r=3.8, d=1.8)

ic = reg(Icon('view_family_group'))
ic.ring(31, 30, 21, tone='slate', ink='slate', w=2.2, dash='3.5 3.8')
for (x, y), kind in zip(((22, 22), (40, 22), (22, 38), (40, 38)), ('sq', 'ci', 'ci', 'sq')):
    node(ic, kind, x, y, 'other', r=5.2, d=2.2)
node(ic, 'di', 31, 30, 'rel', r=4.4, d=2)

ic = reg(Icon('view_list'))
for y, kind in ((14, 'sq'), (31, 'ci'), (48, 'di')):
    node(ic, kind, 14, y, 'other', r=5.2, d=2.2)
    ic.solid(Block(25, y - 3.4, 30 - 4 * (y == 31), 6.8, r=3.4), 'mist', d=2)

ic = reg(Icon('view_cards'))
ic.solid(Block(10, 12, 34, 28, r=5), 'mist', d=2.4)
ic.solid(Block(16, 18, 34, 28, r=5), 'slate', d=2.4)
ic.solid(Block(22, 24, 34, 28, r=5), 'indigo', d=2.6)
node(ic, 'ci', 39, 36, 'rel', r=5.0, d=0)
ic.line(30, 47, 48, 47, w=2.4, op=0.8)

ic = reg(Icon('view_timeline'))
ic.link([(15, 10), (15, 52)])
for y, hue, w in ((14, 'orange', 30), (32, 'mist', 20), (50, 'mist', 26)):
    ic.solid(Disc(15, y, 4.6), hue, d=2)
    ic.solid(Block(26, y - 3.3, w, 6.6, r=3.3), hue, d=2)

# ------------------------------------------------------------------ action_
def disc_badge(stem, hue, fn, **kw):
    ic = Icon(stem)
    ic.solid(Disc(31, 30, 19.5), hue, d=4)
    fn(ic, 31, 30, **kw)
    return reg(ic)


def tile(stem, hue, fn, **kw):
    ic = Icon(stem)
    ic.solid(Block(12, 10, 39, 39, r=9.5), hue, d=4)
    fn(ic, 31.5, 29.5, **kw)
    return reg(ic)


disc_badge('action_add', 'green', g('plus'))
disc_badge('action_delete', 'red', g('cross'))
tile('action_edit', 'teal', g('edit'))
tile('action_search', 'indigo', g('search'))
tile('action_filter', 'ink', g('filter'))
tile('action_zoom_in', 'sky', g('zoom_in'))
tile('action_zoom_out', 'sky', g('zoom_out'))
tile('action_expand', 'orange', g('expand'))
tile('action_collapse', 'orange', g('collapse'))
tile('action_share', 'green', g('share'))
tile('action_download', 'blue', g('download'))
tile('action_upload', 'blue', g('upload'))
tile('action_print', 'slate', g('print'))
tile('action_sync', 'green', g('refresh'))
tile('action_import', 'violet', g('arrow_in'))
tile('action_export', 'violet', g('arrow_out'))
tile('action_undo', 'amber', g('undo'))
tile('action_redo', 'amber', g('redo'))
tile('action_save', 'indigo', g('save'))
tile('action_link', 'teal', g('link'))
tile('action_unlink', 'red', g('unlink'))
tile('action_merge', 'pink', g('merge'))
tile('action_split', 'pink', g('split'))
tile('action_copy', 'ink', g('copy'))

ic = reg(Icon('action_duplicate'))
node(ic, 'ci', 24, 24, 'other', r=8, d=2.4)
node(ic, 'ci', 38, 37, 'rel', r=8, d=2.6)

ic = reg(Icon('action_connect'))
ic.link([(16, 31), (46, 31)], w=3)
node(ic, 'ci', 16, 31, 'other', r=7, d=3)
node(ic, 'ci', 46, 31, 'other', r=7, d=3)
ic.solid(Disc(31, 31, 6.2), 'green', d=2)
ic.plus(31, 31, 3.2, w=2.4)

ic = reg(Icon('action_disconnect'))
ic.link([(16, 31), (25, 31)], w=3)
ic.link([(37, 31), (46, 31)], w=3)
node(ic, 'ci', 16, 31, 'other', r=7, d=3)
node(ic, 'ci', 46, 31, 'other', r=7, d=3)
ic.cross(31, 31, 3.6, w=2.6, tone='red', ink='red')

ic = reg(Icon('action_share_tree'))
ic.link([(22, 14), (22, 24), (12, 24), (12, 33)], w=2.2)
ic.link([(22, 24), (32, 24), (32, 33)], w=2.2)
node(ic, 'ci', 22, 14, 'rel', r=4.8, d=2)
node(ic, 'ci', 12, 36, 'other', r=4.4, d=2)
node(ic, 'ci', 32, 36, 'other', r=4.4, d=2)
ic.solid(Disc(46, 44, 10), 'green', d=2.6)
ic.line(42, 44, 50, 39.5, w=1.8)
ic.line(42, 44, 50, 48.5, w=1.8)
for px, py in ((42, 44), (50, 39.5), (50, 48.5)):
    ic.dot(px, py, 2.4)


def _add_rel(stem, parts):
    ic = Icon(stem)
    for p in parts:
        p(ic)
    ic.solid(Disc(47, 46, 8.6), 'green', d=2.4)
    ic.plus(47, 46, 4.2, w=2.8)
    return reg(ic)


_add_rel('action_add_parent', [lambda ic: ic.link([(22, 14), (22, 40)], w=2.4),
                               lambda ic: node(ic, 'di', 22, 14, 'rel', r=6.4, d=2.6),
                               lambda ic: node(ic, 'ci', 22, 40, 'self', r=6.4, d=2.6)])
_add_rel('action_add_child', [lambda ic: ic.link([(22, 14), (22, 40)], w=2.4),
                              lambda ic: node(ic, 'ci', 22, 14, 'self', r=6.4, d=2.6),
                              lambda ic: node(ic, 'di', 22, 40, 'rel', r=6.4, d=2.6)])
_add_rel('action_add_sibling', [lambda ic: ic.link([(22, 13), (22, 21), (10, 21), (10, 34)], w=2.4),
                                lambda ic: ic.link([(22, 21), (34, 21), (34, 34)], w=2.4),
                                lambda ic: node(ic, 'di', 22, 13, 'other', r=5.4, d=2.4),
                                lambda ic: node(ic, 'ci', 10, 38, 'self', r=5.4, d=2.4),
                                lambda ic: node(ic, 'di', 34, 38, 'rel', r=5.4, d=2.4)])
_add_rel('action_add_spouse', [lambda ic: ic.link([(14, 28), (36, 28)], 'double'),
                               lambda ic: node(ic, 'ci', 14, 28, 'self', r=6.4, d=2.6),
                               lambda ic: node(ic, 'di', 36, 28, 'rel', r=6.4, d=2.6)])

# ------------------------------------------------------------------ ui_
ic = Icon('ui_verified')
ic.solid(Disc(31, 30, 19.5), 'green', d=4)
ic.check(31, 30, 9.5, w=3.6)
reg(ic)

ic = Icon('ui_unverified')
ic.solid(Disc(31, 30, 19.5), 'slate', d=4)
ic.ring(31, 30, 11, w=2.6, dash='3 3.5')
reg(ic)

tile('ui_notes', 'amber', g('document'))
tile('ui_privacy', 'ink', g('shield'))
tile('ui_settings', 'ink', g('sliders'))
tile('ui_menu', 'slate', g('menu'))
tile('ui_photo', 'sky', g('image'))
tile('ui_place', 'green', g('pin'))
tile('ui_dna', 'violet', g('dna'))
tile('ui_source', 'brown', g('tray'))
tile('ui_calendar', 'red', g('calendar'))

ic = reg(Icon('ui_profile'))
ic.solid(Disc(31, 18, 9), 'sky', d=3)
ic.solid(Block(15, 31, 32, 21, r=10.5), 'blue', d=3)


def _doc(stem, hue, mark):
    ic = Icon(stem)
    ic.solid(RPoly([(15, 8), (35, 8), (47, 20), (47, 51), (15, 51)], r=3.5), hue, d=3.5)
    ic.solid(RPoly([(35, 8), (47, 20), (35, 20)], r=1.5), 'paper', d=0)
    mark(ic)
    ic.solid(Disc(46, 47, 8.5), 'indigo', d=2.4)
    ic.arrow(42.5, 50.5, 49.5, 43.5, head=3.4)
    return reg(ic)


_doc('ui_export_pdf', 'red', lambda ic: ic.bars(22, 30, [19, 19, 11], 6.5))
_doc('ui_export_csv', 'green', lambda ic: ic.mark('M21 29H41M21 36H41M21 43H41M31 24V47', w=1.8))
