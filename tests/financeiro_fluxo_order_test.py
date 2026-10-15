"""Valida a expressão SQL de ordenação em SQLite, sem acesso ao banco do site."""
from pathlib import Path
import re
import sqlite3

source = Path('admin/_financeiro_filtros.php').read_text(encoding='utf-8')
expression = re.search(r'return "(CASE WHEN \$column IS NULL.*?)";', source, re.S).group(1)
db = sqlite3.connect(':memory:')
db.execute('CREATE TABLE lancamentos (id INTEGER, vencimento TEXT, data_lancamento TEXT)')
db.executemany('INSERT INTO lancamentos VALUES (?,?,?)', [
    (1, '2026-09-30', '2026-08-01'),
    (2, '2026-10-01', '2026-08-02'),
    (3, '2026-10-31', '2026-08-03'),
    (4, '2026-11-01', '2026-08-04'),
    (5, '2027-01-01', '2026-08-05'),
    (6, '2026-01-01', '2026-08-06'),
    (7, None, '2026-10-15'),
    (8, None, None),
    (9, '2026-11-02', '2026-10-10'),
])
def order(column):
    return expression.replace('$column', column).replace('$start', '2026-10-01').replace('$next', '2026-11-01')
ids = lambda query: [r[0] for r in db.execute(query)]
assert ids('SELECT id FROM lancamentos ORDER BY ' + order('COALESCE(vencimento, data_lancamento)')) == [2, 7, 3, 4, 9, 5, 1, 6, 8]
assert ids('SELECT id FROM lancamentos ORDER BY ' + order('data_lancamento')) == [9, 7, 6, 5, 4, 3, 2, 1, 8]
assert ids("SELECT id FROM lancamentos WHERE COALESCE(vencimento, data_lancamento) >= '2026-10-01' AND COALESCE(vencimento, data_lancamento) < '2026-11-01' ORDER BY " + order('COALESCE(vencimento, data_lancamento)')) == [2, 7, 3]
print('PASS: mes atual, futuros, historico recente primeiro, datas ausentes, base de emissao e limites do mes.')
