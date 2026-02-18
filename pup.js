const puppeteer = require('puppeteer');

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

    const uri = (process.argv[2] || 'https://www.find-a-voice.de/');
    
    const browser = await puppeteer.launch({
      headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });
    const page = await browser.newPage();
    await page.setJavaScriptEnabled(true);
    await page.goto(uri);
    await page.setViewport({
      width: 1200,
      height: 800
  });
    await page.waitForSelector('body', { timeout: 5_000 });
    await autoScroll(page, 10);
    await page.screenshot({path: '/var/www/project/public/dist/img/screen.png',fullPage:true});
    await page.waitForNetworkIdle();
    // ist vielleicht ein button und vielleicht ist der Text nicht "2"
    //const nextPage = await getByText(page, "a", "2");
    //await nextPage.click();
    //await page.waitForNetworkIdle();

    await page.screenshot({path: '/var/www/project/public/dist/img/screen2.png',fullPage:true});
    let stuff = await page.content();



    
   // const bodyHandle = await page.$('body');
    //const stuff = await page.evaluate(body => body.innerHTML, bodyHandle);
    //await bodyHandle.dispose();

   //  const stuff = await page.evaluate(() => 
   //    document.querySelector('.favdescription').outerHTML
   // favdescription fehlt
    // );
  
    //Only for testing
    await browser.close(); 
    return await stuff;
}
}
module.exports.mypup().then((r)=>(console.log(r)))
