// =====================================================
// 裏側ビュー：スマホ・LINE社・自社サーバーの間で起きたことを時間順に表示
// =====================================================
const express = require('express');
const log = require('./log');

const router = express.Router();

router.get('/', (req, res) => res.render('inside/index', { title: '裏側ビュー', logs: log.list(0, 300), SIDES: log.SIDES }));
router.get('/logs.json', (req, res) => res.json(log.list(Number(req.query.after) || 0, 300)));
router.post('/clear', (req, res) => { log.clear(); res.redirect('/inside'); });

module.exports = router;
