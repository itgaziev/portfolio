function ITGaziev_WbPrice (data, defaultValue = []) {
    this.data = data
    this.defaultValue = defaultValue

    this.init = () => {
        this.eventReload();
        this.setDefault()
    }

    this.setDefault = () => {
        this.defaultValue.forEach((value, index) => {
            let dom = document.querySelector('[data-entity="row-field"]')
            let template = this.getTemplateRow(index, value)
            dom.insertAdjacentHTML("beforeend", template)
            $('[name="PARAMETERS['+index+'][VALUE]"]').select2({data : this.data.select, width : 'style', placeholder : 'Выберите значение'});
        })
        this.eventReload()
    }

    this.addField = (e) => {
        let index = this.reindexColumn()
        let dom = document.querySelector('[data-entity="row-field"]')
        let template = this.getTemplateRow(index)
        dom.insertAdjacentHTML("beforeend", template)
        $('[name="PARAMETERS['+index+'][VALUE]"]').select2({data : this.data.select, width : 'style', placeholder : 'Выберите значение'});
        this.eventReload()
    }

    this.reindexColumn = () => {
        let index = 0;
        document.querySelectorAll('[data-entity="column-field"]').forEach(element => {
            console.log(index)
            element.dataset.index = index;
            let select = element.querySelector('[data-entity="select"]')
            select.name = `PARAMETERS[${index}][VALUE]`

            let input = element.querySelector('[data-entity="input"]')
            input.name = `PARAMETERS[${index}][COLUMN]`

            let button = element.querySelector('[data-entity="button"]')
            button.dataset.index = index;

            index++;
        })

        return index;
    }

    this.removeField = (e) => {
        let target = e.target;
        document.querySelector('[data-index="'+target.dataset.index+'"]').remove()
        this.reindexColumn()
    }

    this.eventReload = () => {
        console.log(this.data)
        document.querySelector('.add-btn').addEventListener('click', this.addField)
        document.querySelectorAll('.remove-btn').forEach(element => {
            element.addEventListener('click', this.removeField)
        });
        
    }

    this.getTemplateRow = (index, value = {COLUMN : "", VALUE : ""}) => {
        let template = `
            <tr data-entity="column-field" data-index="${index}">
                <td><input type="text" class="select-column" name="PARAMETERS[${index}][COLUMN]" value="${value.COLUMN}" placeholder="Введите название"  data-entity="input"></td>
                <td>
                    <select name="PARAMETERS[${index}][VALUE]" class="condition-select condition-field-select select-column"  data-selected="${value.VALUE}" data-entity="select"></select>
                </td>
                <td style="text-align: center">
                    <button class="btn remove-btn" type="button" data-index="${index}" data-entity="button">X</button>
                </td>   
            </tr>
        `;

        return template;
    }
}

function lazyCreatePrice(priceId) {
    this.priceId = priceId
    this.count = 0
    this.maxPage = 0
    this.isStoped = true
    this.ajaxUrl = '/bitrix/tools/itgaziev.wbprice/ajax.php';
    this.progressBar = document.getElementById('file')
    this.progressText = document.getElementById('progressText')
    this.fileUrl = document.getElementById('fileUrl')
    this.btn = document.getElementById('toggleBtn');
    this.init = () => {
        console.log(this.priceId)
        this.event();
    }

    this.toggleEvent = (e) => {
        this.isStoped = !this.isStoped;
        if (!this.isStoped) {
            this.btn.classList.add('continue')
            this.btn.innerText = "Остановить";
            this.startExchange()
        }
    }

    this.startExchange = () => {
        fetch(this.ajaxUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ action: 'start', priceId : this.priceId })
        })
        .then(response => response.json())
        //.then(data => console.log(data))
        .then(data => setTimeout(this.infinitUpdate(data), 180))
    }

    this.infinitUpdate = (data) => {
        if (this.isStoped) {
            this.stopExchange();
            return;
        }
        let percentage = 0;
        if (data.total > 0) {
            percentage = (data.current / data.total) * 100;
            console.log(`Процент: ${percentage.toFixed(0)}%`);
        } else {
            console.log("Общее значение должно быть больше нуля.");
        }
        this.progressBar.value = percentage.toFixed(0);
        this.progressText.innerText = "Выполнено: " + data.current + ' / ' + data.total;

        if (data.action == 'continue') {
            fetch(this.ajaxUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'continue', priceId : this.priceId })
            })
            .then(response => response.json())
            .then(data => {
                
                setTimeout(this.infinitUpdate(data), 180)
            })
        } else if(data.action == 'download') {
            fetch(this.ajaxUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'download', priceId : this.priceId })
            })
            .then(response => {
                console.log(response)
                return response.json()
            })
            .then(data => {
                this.fileUrl.href = data.url
                this.fileUrl.classList.remove('dnone')
                setTimeout(this.infinitUpdate(data), 180)
            })
        } else {
            this.fileUrl.classList.remove('dnone')
            console.log('is stopped')
            this.stopExchange()
            this.isStoped = true;
        }
    }

    this.stopExchange = () => {
        this.progressBar.value = 0;
        this.btn.classList.remove('continue')
        this.btn.innerText = "Начать создание";
        this.progressText.innerText = "Ожидание начало генерации";
    }

    this.event = () => {
        this.btn.addEventListener('click', this.toggleEvent);
    }
}