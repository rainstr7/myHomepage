(function app() {
    // Локальные переменные.
    var parentElem = document.getElementById("app");
    var localLinksCountElem = document.getElementById("local-links-count");
    var lettersCountElem = document.getElementById("total-letters-count");
    var allLettersCountElem = document.getElementById("all-letters-count");
    var badLinksCountElem = document.getElementById("bad-links-count");
    var errorMsgElem = document.getElementById("error");
    var xmlUrlElem = document.getElementById("xml-url");
    var stateClass1 = "is-state-1";
    var stateClass2 = "is-state-2";
    var stateClass3 = "is-state-3";
    var stateClassError = "is-state-error";
    var parentElemClassList = parentElem.classList;


    var init = function () {
        // Получаем ссылку на XML-документ из get-параметров.
        var xmlUrl = (function getXmlParam() {
            var loc = window.location;
            var query = loc.search.substr(1);
            if (!query) {
                return false;
            }
            var locParams = {};
            query.split("&").forEach(function (part) {
                var split = part.split("/");
                locParams[split[0]] = split[1];
            });

            var xml = String(locParams["XML="]);
            var res;

            //Валидация параметр xml (файл  может быть как локальным, так и удалённым)
            if (xml && xml.indexOf(".xml") === xml.length - 4) {
                res = xml;
            } else {
                res = false;
            }
            return res;
        })();

        // Выводим сообщение об ошибке, если параметр отсутсвует или задан неверно.
        if (!xmlUrl) {
            console.log("%cmissed xml query parameter (or wrong value)", "color: red");
            parentElemClassList.remove(stateClass1);
            parentElemClassList.add(stateClassError);
            errorMsgElem.textContent = "Параметр xml не задан, или его значение неверно. Проверьте правильность указанного пути к файлу (например https://localhost/litrestest.html?XML=/test.xml)";
            return;
        }

        // Отображаем ссылку на документ на index.html
        xmlUrlElem.textContent = xmlUrl;
        xmlUrlElem.href = xmlUrl;

        // Парсим XML.
        var xmlParser = (function getXmlParser() {
            var res;
            if (typeof window.DOMParser != "undefined") {
                res = function (xmlStr) {
                    return (new window.DOMParser()).parseFromString(xmlStr, "text/xml");
                };
            } else if (typeof window.ActiveXObject != "undefined" &&
                new window.ActiveXObject("Microsoft.XMLDOM")) {
                res = function (xmlStr) {
                    var xmlDoc = new window.ActiveXObject("Microsoft.XMLDOM");
                    xmlDoc.async = "false";
                    xmlDoc.loadXML(xmlStr);
                    return xmlDoc;
                };
            } else {
                res = false;
            }
            return res;
        })();

        // Если возникли проблемы парсера, выводим сообщение об ошибке.
        if (!xmlParser) {
            console.log("%сno xml parser found", "color: red");
            parentElemClassList.remove(stateClass1);
            parentElemClassList.add(stateClassError);
            errorMsgElem.textContent = "Ошибка. Возникла ошибка инициализации парсера xml";
            return;
        }

        console.log("xmlUrl: " + xmlUrl);

        // Генерируем запрос к указанному файлу.
        var xhr = new XMLHttpRequest();
        xhr.open("GET", xmlUrl, true);
        var xmlObject;
        xhr.onreadystatechange = function (response) {
            if (xhr.readyState !== 4) {
                return;
            }

            console.log("request done");
            parentElemClassList.remove(stateClass2);

            // Если при загрузке возникли ошибки, то выводим соотвествующее сообщение и прекращаем дальнейшую обработку файла.
            if (xhr.status !== 200) {
                parentElemClassList.add(stateClassError);
                var statusText = xhr.statusText || "Ошибка. Возникла ошибка при загрузке файла";
                if (xhr.status === 404) {
                    statusText = "Ошибка. Указанный файл не найден";
                }
                errorMsgElem.textContent = statusText;
                return;
            }

            // На всякий случай, оборачиваем парсер в try...catch, и, при возникновении ошибок, выводим соответствующее сообщение.
            try {
                parseXmlData(xmlParser(xhr.responseText));
            } catch (e) {
                console.error(e);
                parentElemClassList.add(stateClassError);
                errorMsgElem.textContent = e;
            }
            var book = document.getElementById('book');
            book.innerHTML = xhr.responseText;
        }

        console.log("request send");
        // Меняем статус приложения, с "Подготовка..." на "Работаю с xml-документом..."
        parentElemClassList.remove(stateClass1);
        parentElemClassList.add(stateClass2);
        xhr.send();
    };

    // Парсим XML-данные.
    var parseXmlData = function (xml) {
        if (!xml || typeof xml !== "object") {
            return;
        }

        var documentElement = xml.documentElement;

        // Задача 1: получение количества локальных ссылок.
        var allLinks = documentElement.querySelectorAll("a");
        var localLinks = Array.prototype.filter.call(allLinks, function (link) {
            return link.getAttribute("l:href") && link.getAttribute("l:href").indexOf("#") === 0;
        });
        var localLinksCount = localLinks.length;
        console.log("localLinksCount: " + localLinksCount);
        localLinksCountElem.textContent = localLinksCount;

        // Задача 2: получение количества букв в тегах без учета пробелов.
        var childNodes = documentElement.querySelectorAll(':scope *');;
        var lettersCount = 0;
        Array.prototype.forEach.call(childNodes, function (elem) {
            var textContent = elem.textContent;
            if (!textContent || typeof textContent !== "string") {
                return;
            }
            var textContentLetters = textContent.replace(/\s/g, "");
            lettersCount += textContentLetters.length;
        });
        console.log("lettersCount: " + lettersCount);
        lettersCountElem.textContent = lettersCount;

        // Задача 3 получение количества всех букв в тегах.
        var childNodes = documentElement.querySelectorAll(':scope *');;
        var allLettersCount = 0;
        Array.prototype.forEach.call(childNodes, function (elem) {
            var textContent = elem.textContent;
            if (!textContent || typeof textContent !== "string") {
                return;
            }
            var allTextContentLetters = textContent;
            allLettersCount += allTextContentLetters.length;
        });
        console.log("allLettersCount: " + allLettersCount);
        allLettersCountElem.textContent = allLettersCount;

        // Задача 4: получение количества битых ссылок.
        var badLinksCount = 0;
        Array.prototype.forEach.call(localLinks, function (link) {
            var href = link.getAttribute("l:href").trim();
            if (!documentElement.querySelector(href)) {
                badLinksCount += 1;
            }
        });
        console.log("badLinksCount: " + badLinksCount);
        badLinksCountElem.textContent = badLinksCount;

        // Когда все задачи выполнены, переводим статус приложения с "идёт обработка" на "Я закончил".
        parentElemClassList.add(stateClass3);
    };
    init();
})();

function show() {
    var div = document.getElementById('book')
    if (div.style.display == '') {
        document.getElementById('show').innerHTML = "Показать документ читабельном виде";
        div.style.display = 'none';
    } else {
        document.getElementById('show').innerHTML = "Скрыть документ";
        div.style.display = '';
    }
}