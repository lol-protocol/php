#!/usr/bin/env python3
"""Catalogo abstracto de genealogia: diagramas de parentesco sin texto.

Gramatica visual: naranja = el familiar, azul oscuro con punto = tu, gris claro = otros;
cuadrado = hombre, circulo = mujer, rombo = sin especificar; linea doble = matrimonio,
linea cortada = divorcio, discontinua = adopcion o parentesco parcial, punteada = acogida.
"""
import math

from kit import badge, tile
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
            elif mark == 'dot':
                ic.dot(mx, my, 3.4, tone='orange', ink='orange')
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
    ('person_adoptive_father', 'm', True, 'dashed', 'dot'), ('person_adoptive_mother', 'f', True, 'dashed', 'dot'),
    ('person_adopted_son', 'm', False, 'dashed', 'dot'), ('person_adopted_daughter', 'f', False, 'dashed', 'dot'),
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
s.n('a', 'm', 0, 0, 'rel2').n('b', 'f', 1, 0, 'rel2')
s.decor.append(lambda ic, pos, r: ic.link([(x, pos['a'][1] + 4.2 * math.sin((x - pos['a'][0]) / (pos['b'][0] - pos['a'][0]) * 3 * math.pi))
                                          for x in [pos['a'][0] + (pos['b'][0] - pos['a'][0]) * i / 40 for i in range(41)]], w=2.6, hue='rose'))
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
ic.link([(15, 30), (47, 30)], w=6.4, hue='red')
node(ic, 'di', 15, 30, 'rel', r=7.4, d=3)
node(ic, 'di', 47, 30, 'rel', r=7.4, d=3)

s = scene('relationship_adoption')
s.n('a', 'm', 0, 0).n('b', 'f', 2, 0).n('c', 'u', 1, 1, 'rel')
s.couple('a', 'b').desc(('a', 'b'), 'c', 'dashed', 'dot')
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
# Las acciones son piezas sueltas con movimiento: lo que se ve es de dónde viene y a dónde va, nunca un objeto.
def pt_on(cx, cy, r, a):
    return cx + r * math.cos(math.radians(a)), cy + r * math.sin(math.radians(a))


def new(stem):
    return reg(Icon(stem))


reg(badge('action_add', 'green', lambda ic: ic.plus(31, 30, 10, w=4)))
reg(badge('action_delete', 'red', lambda ic: ic.cross(31, 30, 8.5, w=4)))

ic = new('action_edit')
ic.solid(Block(13, 26, 38, 10, r=5, rot=-45), 'teal', d=3)
ic.solid(Disc(15.5, 46, 3.6), 'ink', d=1.6)

ic = new('action_search')
ic.solid(Disc(43, 43, 5.5), 'indigo', d=2.4)
ic.ring(27, 26, 12, tone='indigo', ink='indigo', w=4.6)

ic = new('action_filter')
for x, y, w in ((8, 11, 46), (15, 25, 32), (22, 39, 18)):
    ic.solid(Block(x, y, w, 9, r=4.5), 'ink', d=2.4)


def _lens(stem, r, sign):
    """Anillo con un punto en diagonal; el tamaño del anillo dice si acerca o aleja."""
    k = 0.707 * (r + 4.5)
    cx = 31 - (k + 5.5 - r - 2.3) / 2
    cy = 30 - (k + 5.5 - r - 2.3) / 2
    ic = new(stem)
    ic.solid(Disc(cx + k, cy + k, 5.5), 'sky', d=2.4)
    ic.ring(cx, cy, r, tone='sky', ink='sky', w=4.6)
    ic.mark(f'M{fmt(cx - 0.5 * r)} {fmt(cy)}h{fmt(r)}' + (f'M{fmt(cx)} {fmt(cy - 0.5 * r)}v{fmt(r)}' if sign else ''), tone='sky', ink='sky', w=3)


_lens('action_zoom_in', 14, True)
_lens('action_zoom_out', 9.5, False)


def _spread(stem, far):
    ic = new(stem)
    k = 19 if far else 11.5
    for dx, dy in ((-1, -1), (1, -1), (-1, 1), (1, 1)):
        ic.solid(Disc(31 + dx * k, 30 + dy * k, 3.8), 'orange', d=1.8)
    ic.solid(Disc(31, 30, 7.5), 'orange', d=2.8)


_spread('action_expand', True)
_spread('action_collapse', False)

ic = new('action_share')
for x, y in ((47, 14), (51, 31), (47, 48)):
    ic.link([(15, 31), (x, y)], w=2.2)
ic.solid(Disc(15, 31, 8), 'green', d=3)
for x, y in ((47, 14), (51, 31), (47, 48)):
    ic.solid(Disc(x, y, 4.6), 'green', d=2)

ic = new('action_download')
ic.mark('M23.5 7v4M31 5v4M38.5 7v4', tone='blue', ink='blue', w=2.8)
ic.solid(Disc(31, 24, 7), 'blue', d=2.6)
ic.solid(Block(11, 42, 42, 8, r=4), 'ink', d=3)

ic = new('action_upload')
ic.solid(Disc(31, 14, 7), 'blue', d=2.6)
ic.mark('M23.5 27v5M31 27v7M38.5 27v5', tone='blue', ink='blue', w=2.8)
ic.solid(Block(11, 42, 42, 8, r=4), 'ink', d=3)

ic = new('action_print')
for dx, dy, op, d in ((10, 10, 0.35, 0), (5, 5, 0.6, 0), (0, 0, 1.0, 3.5)):
    ic.solid(Block(14 + dx, 14 + dy, 26, 26, r=6), 'slate', d=d, op=op)

ic = new('action_sync')
for a0, a1 in ((-170, -20), (10, 160)):
    ic.arc(31, 30, 14, a0, a1, tone='green', ink='green', w=4.4)
    x, y = pt_on(31, 30, 14, a1)
    ic.solid(Disc(x, y, 3.6), 'green', d=1.4)

ic = new('action_import')
ic.mark('M7 30h3M12.5 30h3', tone='violet', ink='violet', w=2.8)
ic.solid(Disc(24, 30, 6), 'violet', d=2.4)
ic.solid(Block(37, 12, 19, 36, r=6), 'ink', d=3)

ic = new('action_export')
ic.solid(Block(8, 12, 18, 36, r=6), 'ink', d=3)
ic.mark('M30 30h4', tone='violet', ink='violet', w=2.8)
ic.solid(Disc(43, 30, 6), 'violet', d=2.4)


def _turn(stem, back):
    ic = new(stem)
    a0, a1 = (190, 350) if back else (190, 350)
    ic.arc(31, 36, 17, a0, a1, tone='amber', ink='amber', w=4.4)
    x, y = pt_on(31, 36, 17, 190 if back else 350)
    ic.solid(Disc(x, y, 4.6), 'amber', d=1.8)


_turn('action_undo', True)
_turn('action_redo', False)

ic = new('action_save')
ic.solid(Block(11, 11, 42, 42, r=9), 'indigo', d=4)
ic.dot(31.5, 28, 6.5)
ic.line(23, 42, 40, 42, w=3.4)

ic = new('action_link')
ic.solid(Disc(23, 30, 12), 'teal', d=3)
ic.solid(Disc(39, 30, 12), 'teal', d=3)

ic = new('action_unlink')
ic.solid(Disc(16, 30, 10.5), 'red', d=3)
ic.solid(Disc(46, 30, 10.5), 'red', d=3)
ic.mark('M28.5 30h2M33.5 30h2', tone='red', ink='red', w=2.6)

ic = new('action_merge')
ic.link([(14, 19), (44, 30)], w=2.6)
ic.link([(14, 41), (44, 30)], w=2.6)
ic.solid(Disc(14, 19, 5.2), 'pink', d=2)
ic.solid(Disc(14, 41, 5.2), 'pink', d=2)
ic.solid(Disc(44, 30, 10), 'pink', d=3)

ic = new('action_split')
ic.link([(20, 30), (50, 19)], w=2.6)
ic.link([(20, 30), (50, 41)], w=2.6)
ic.solid(Disc(20, 30, 10), 'pink', d=3)
ic.solid(Disc(50, 19, 5.2), 'pink', d=2)
ic.solid(Disc(50, 41, 5.2), 'pink', d=2)

ic = new('action_copy')
ic.solid(Block(8, 17, 28, 30, r=6), 'ink', d=0, op=0.45)
ic.solid(Block(24, 17, 28, 30, r=6), 'ink', d=3.5)

ic = new('action_duplicate')
node(ic, 'ci', 24, 24, 'other', r=8, d=2.4)
node(ic, 'ci', 38, 37, 'rel', r=8, d=2.6)

ic = new('action_connect')
ic.link([(16, 31), (46, 31)], w=3)
node(ic, 'ci', 16, 31, 'other', r=7, d=3)
node(ic, 'ci', 46, 31, 'other', r=7, d=3)
ic.solid(Disc(31, 31, 6.2), 'green', d=2)
ic.plus(31, 31, 3.2, w=2.4)

ic = new('action_disconnect')
ic.link([(16, 31), (25, 31)], w=3)
ic.link([(37, 31), (46, 31)], w=3)
node(ic, 'ci', 16, 31, 'other', r=7, d=3)
node(ic, 'ci', 46, 31, 'other', r=7, d=3)
ic.cross(31, 31, 3.6, w=2.6, tone='red', ink='red')

ic = new('action_share_tree')
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
reg(badge('ui_verified', 'green', lambda ic: ic.dot(31, 30, 11.5)))
reg(badge('ui_unverified', 'slate', lambda ic: ic.ring(31, 30, 11, w=2.6, dash='3 3.5')))

reg(tile('ui_notes', 'amber', lambda ic, x, y: ic.bars(x - 11, y - 9.5, [22, 15, 19], 9.5, w=3.2)))


def _privacy(ic, x, y):
    ic.poly([(x - 12, y - 12), (x + 12, y - 12), (x + 12, y + 12), (x - 12, y + 12)], True, w=3)
    ic.dot(x, y, 4.6)


reg(tile('ui_privacy', 'ink', _privacy))


def _sliders(ic, x, y):
    for dy, kx in ((-8, -4), (0, 4), (8, -1)):
        yy = y + dy
        ic.mark(f'M{fmt(x - 12.5)} {fmt(yy)}H{fmt(x + kx - 3.6)}M{fmt(x + kx + 3.6)} {fmt(yy)}H{fmt(x + 12.5)}', w=2)
        ic.ring(x + kx, yy, 2.4, w=2)


reg(tile('ui_settings', 'ink', _sliders))
reg(tile('ui_menu', 'slate', lambda ic, x, y: ic.mark(f'M{fmt(x - 9.5)} {fmt(y - 7.5)}h19M{fmt(x - 9.5)} {fmt(y)}h19M{fmt(x - 9.5)} {fmt(y + 7.5)}h19', w=3)))


def _photo(ic, x, y):
    ic.poly([(x - 11, y - 10), (x + 11, y - 10), (x + 11, y + 10), (x - 11, y + 10)], True, w=2.2)
    ic.poly([(x - 8, y + 7), (x - 3, y), (x + 2, y + 7)], True, filled=True)
    ic.dot(x + 5.5, y - 4.5, 2.4)


reg(tile('ui_photo', 'sky', _photo))

ic = new('ui_place')
ic.ring(31, 40, 14, tone='green', ink='green', w=3.4)
ic.solid(Disc(31, 24, 9.5), 'green', d=3)


def _helix(ic, x, y):
    for ph in (0, math.pi):
        pts = [(x + 8 * math.sin(2 * math.pi * t / 24 + ph), y - 14 + 28 * t / 24) for t in range(25)]
        ic.poly(pts, w=2.6)
    ic.mark(f'M{fmt(x - 8)} {fmt(y - 7)}h16M{fmt(x - 8)} {fmt(y + 7)}h16', w=2)


reg(tile('ui_dna', 'violet', _helix))

ic = new('ui_source')
for x, y in ((9, 38), (16, 26), (23, 14)):
    ic.solid(Block(x, y, 30, 10, r=5), 'brown', d=2.4)

reg(tile('ui_calendar', 'red', lambda ic, x, y: [ic.dot(x - 10 + 10 * (i % 3), y - 9.5 + 9.5 * (i // 3), 2, **({'tone': '#FDE047'} if i == 4 else {})) for i in range(9)]))

ic = new('ui_profile')
node(ic, 'ci', 31, 30, 'self', r=15, d=4)


def _doc(stem, hue, mark):
    ic = Icon(stem)
    ic.solid(Block(10, 8, 40, 42, r=8), hue, d=4)
    mark(ic)
    ic.solid(Disc(48, 47, 8.5), 'indigo', d=2.4)
    ic.dot(48, 47, 2.8)
    return reg(ic)


_doc('ui_export_pdf', 'red', lambda ic: ic.bars(19, 20, [22, 22, 12], 9.5, w=3.2))
_doc('ui_export_csv', 'green', lambda ic: ic.mark('M19 22H41M19 31H41M19 40H41M24.5 17V44M35.5 17V44', w=2))
