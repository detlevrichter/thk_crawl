const puppeteer = require('puppeteer');
const fs = require('fs');
const debugLogSetting = false;
const logFile = __dirname + '/pup-debug.log';
function debugLog(msg) {
    try {
        if(debugLogSetting){
            fs.appendFileSync(logFile, `[${new Date().toISOString()}] ${msg}\n`);
        }
    } catch (e) {}
}

module.exports = {
   mypup : async function(url) {
        const  sleep = (ms)=> {
        return new Promise(resolve => setTimeout(resolve, ms));
        }
        const autoScroll = async (page, maxScrolls) => {
        await page.evaluate(async (maxScrolls) => {
            await new Promise((resolve) => {
                var totalHeight = 0;
                var distance = 100;
                var scrolls = 0;  // scrolls counter
                var timer = setInterval(() => {
                    var scrollHeight = document.body.scrollHeight;
                    window.scrollBy(0, distance);
                    totalHeight += distance;
                    scrolls++;  // increment counter

                    // stop scrolling if reached the end or the maximum number of scrolls
                    if(totalHeight >= scrollHeight - window.innerHeight || scrolls >= maxScrolls){
                        clearInterval(timer);
                        resolve();
                    }
                }, 100);
            });
        }, maxScrolls);  // pass maxScrolls to the function
        }
        const performActions = async (page, actions) => {
            if (
                actions &&
                !Array.isArray(actions) &&
                Object.keys(actions).length === 0
            ) {
                return;
            }
            // Ein einzelnes Objekt oder ein Array akzeptieren
            if (!Array.isArray(actions)) {
                actions = [actions];
            }


            for (const action of actions) {
                switch (action.action) {

                    case "click":

                        await page.waitForSelector(action.selector);
                        await page.evaluate(selector => {
                                document.querySelector(selector)?.click();
                            }, action.selector);
                        await page.screenshot({path:  __dirname + '/public/dist/img/screen3.png',fullPage:true});


                        break;


                    case "clickUntilStable": {

                        const maxClicks = action.maxClicks ?? 5;

                        for (let i = 0; i < maxClicks; i++) {

                            // Prüfen, ob der "Mehr laden"-Container bereits versteckt ist


                            // Button suchen
                            const button = await page.$(action.selector);

                            if (!button) {
                                break;
                            }

                          //  await button.click();
                            await page.evaluate(selector => {
                                document.querySelector(selector)?.click();
                            }, action.selector);

                            // Warten bis neue Inhalte geladen wurden
                            await page.waitForNetworkIdle();

                        }

                        break;
                    }

                    default:
                        throw new Error(
                            `Unbekannte Action: ${JSON.stringify(action)}`
                        );
                    }
                }
            }

        const uri = (process.argv[2] || 'http://zomboo.com');
        const params = JSON.parse(process.argv[3] || "{}");
        const cleanUri = uri.replace(/&amp;/g, '&');
        const cleanUrl = new URL(cleanUri).href;
        const browser = await puppeteer.launch({
        headless: true,
        executablePath: process.env.CHROME_PATH || undefined,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
        });
        try{
            const page = await browser.newPage();
            await page.setJavaScriptEnabled(true);
            await page.setViewport({ width: 1200, height: 800 });
            const response = await page.goto(cleanUrl, { waitUntil: 'networkidle2' });
            debugLog(`URL: ${cleanUrl} STATUS: ${response.status()} HEADERS: ${JSON.stringify(response.headers())}`);
            await page.waitForSelector('body', { timeout: 5_000 });
            await autoScroll(page, 10);
            await page.screenshot({path:  __dirname + '/public/dist/img/screen.png',fullPage:true});
            await page.waitForNetworkIdle();
            await performActions(page, params);
            // ist vielleicht ein button und vielleicht ist der Text nicht "2"
            //const nextPage = await getByText(page, "a", "2");
            //await nextPage.click();
            //await page.waitForNetworkIdle();

            await page.screenshot({path:  __dirname + '/public/dist/img/screen2.png',fullPage:true});
            await page.evaluate(() => {

                const selectors = [
                    'head',
                    'nav',
                    'footer',
                    'header',
                    'aside',
                    'script',
                    '.navigation',
                    '.menu',
                    '.sidebar',
                    '.footer',
                    '.header',

                    '#navigation',
                    '#menu',
                    '#sidebar',
                    '#footer',
                    '#header',

                    '.cookie',
                    '.cookies',
                    '.cookie-banner',
                    '#cookie-banner',

                    '.advertisement',
                    '.ads',
                    '.banner'
                ];

                selectors.forEach(selector => {
                    document.querySelectorAll(selector).forEach(el => el.remove());
                });

            });

            let stuff = await page.content();
            if (stuff.includes("Access Denied") || stuff.includes("Es ist ein Fehler aufgetreten") || stuff.length < 500 || !response.ok()) {
            debugLog(`CONTENT-CHECK-FAILED: len=${stuff.length} status=${response.status()}`);
            return 'FALSE Access Denied';
            }

            await page.close();
            await browser.close();
            return await stuff;

        } catch (error) {
            debugLog(`EXCEPTION: ${error.message}`);
            return 'ERROR: ' + error.message;
        } finally {
            // Dieser Block wird IMMER ausgeführt
            await browser.close();
        }
    }
}

if (require.main === module) {
    module.exports.mypup(process.argv[2]).then((r)=>(console.log(r)));
}
