;

(function () {  // анонимное замыкание, чтобы не вываливать переменные в глобальное окружение


    let namesLen = 0;

// noinspection JSUnusedGlobalSymbols
    let vm = new /** @noinspection JSValidateTypesInspection */ Vue({
        el: '#content',
        delimiters: ['${', '}'],
        data: {
            isTimeWarningHidden: false,
            isCheckBtnDisabled: false,
            resHeader: '',
            resultAlertVariant: 'success',
            freeNames: [],
            isResHidden: true,
            prBarCounter: 0,
            prBarMax: 0,
            isProgressBarHidden: true,
            isCopyBtnHidden: true,
            copyBtnBadge: '0',
            form: {
                list1: '',
                list2: '',
                defis: false,
                method: 'godaddy'
            },
            methods: [
                {text: 'WhoIs', value: 'whois'},
                {text: 'DNS', value: 'dns'},
                {text: 'DNS->WhoIs', value: 'dns-whois'},
                {text: 'API GoDaddy', value: 'godaddy'}
            ]

        },
        methods: {
            startCheck: function () {
                return startCheck();
            },
            copyToBuffer: function () {
                return copyToBuffer();
            },
        }
    });

    setProgressBar(0, 0);

    function showErrorMsg(msg) {
        vm.resultAlertVariant = 'danger';
        vm.resHeader = msg + '!';
    }

    function showWarningMsg(msg) {
        vm.resultAlertVariant = 'warning';
        vm.resHeader = msg + '!';
    }

    function setProgressBar(curPos, fullLength) {
        if (fullLength) {
            vm.resHeader = 'Проверено: ' + curPos + ' из ' + fullLength;
        } else {
            vm.resHeader = '';
        }
        vm.prBarCounter = curPos;
        vm.prBarMax = fullLength;
    }

    function showProgressBar(flag) {
        if (flag) {
            setProgressBar(0, 0);
            vm.isProgressBarHidden = false;
        } else {
            vm.isProgressBarHidden = true;
        }
    }

    function setCheckDisabled() {
        vm.isTimeWarningHidden = true;
        vm.isCheckBtnDisabled = true;
        vm.resHeader = '';
        vm.isResHidden = false;
        vm.isCopyBtnHidden = true;
    }

    function setCheckEnabled() {
        showProgressBar(false);
        if (vm.freeNames.length > 0) {
            vm.copyBtnBadge = vm.freeNames.length;
            vm.isCopyBtnHidden = false;
        }
        vm.isCheckBtnDisabled = false;
    }

    function startCheck() {
        setCheckDisabled();
        vm.freeNames = [];
        // noinspection JSUnresolvedVariable, JSUnresolvedFunction
        axios.post(
            '/start_request',
            {
                list1: vm.form.list1,
                list2: vm.form.list2,
                defis: vm.form.defis,
                method: vm.form.method
            }
        ).then(function (result) {
            let res = result['data'];
            if (res['code'] > 10) {     // ошибка
                showErrorMsg('Что-то пошло не так: ' + res['msg']);
                setCheckEnabled();
                return;
            }
            if (res['code'] > 0 && res['code'] <= 10) {     // предупреждение
                showWarningMsg(res['msg']);
                setCheckEnabled();
                return;
            }
            namesLen = res['len'];
            vm.resultAlertVariant = 'success';
            showProgressBar(true);
            checkNames(res['req_id'], res['ts'], 0);
        }).catch(function (error) {
            showErrorMsg('Ошибка AJAX: ' + error);
            setCheckEnabled();
        });
    }

    function checkNames(reqId, timeStamp, curPos) {
        setProgressBar(curPos, namesLen);
        // noinspection JSUnresolvedVariable
        axios.get(
            '/get_next_part',
            {
                params: {
                    req_id: reqId,
                    ts: timeStamp
                }

            }
        ).then(function (result) {
            let res = result['data'];
            if (res['code'] > 10) {     // ошибка
                showErrorMsg('Что-то пошло не так: ' + res['msg']);
                setCheckEnabled();
                return;
            }
            vm.freeNames = vm.freeNames.concat(res['freeNames']);
            curPos = res['nextPos'];
            if (!res['complete']) {
                checkNames(reqId, timeStamp, curPos);
                return;
            }
            if (vm.freeNames.length > 0) {
                vm.resHeader = 'Свободно ' + vm.freeNames.length + ' из ' + namesLen + ':';
            } else {
                showWarningMsg('Проверено имен: ' + namesLen + '. Свободных нет');
            }
            setCheckEnabled();
        }).catch(function (error) {
            showErrorMsg('Ошибка AJAX: ' + error);
            setCheckEnabled();
        });
    }

    function copyToBuffer() {
        for (let i = 0; i < 3; i++) {
            let ta = document.getElementById('free_names');
            let range = document.createRange();
            range.selectNode(ta);
            window.getSelection().addRange(range);

            //пытаемся скопировать текст в буфер обмена
            try {
                document.execCommand('copy');
            } catch (err) {
                console.log('Can`t copy, boss');
            }
            //очистим выделение текста
            window.getSelection().removeAllRanges();
        }
    }

} ());
