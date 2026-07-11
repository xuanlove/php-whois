from playwright.sync_api import sync_playwright
import json

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page(viewport={"width": 1280, "height": 900})

    # 收集控制台日志和错误
    console_logs = []
    page_errors = []
    page.on("console", lambda msg: console_logs.append(f"[{msg.type}] {msg.text}"))
    page.on("pageerror", lambda err: page_errors.append(str(err)))

    print("=== 1. 访问首页 ===")
    page.goto('http://localhost:8080/index.html')
    page.wait_for_load_state('networkidle')
    page.wait_for_timeout(1500)
    page.screenshot(path='/workspace/01_home.png')
    print("截图: 01_home.png")

    print("\n=== 2. 检查初始控制台错误 ===")
    if page_errors:
        print("页面错误:")
        for e in page_errors:
            print(f"  {e}")
    else:
        print("无页面错误")
    if console_logs:
        print("控制台日志:")
        for log in console_logs:
            print(f"  {log}")

    print("\n=== 3. 测试查询功能（example.com）===")
    console_logs.clear()
    page_errors.clear()
    page.fill('#domain', 'example.com')
    page.click('#submit-btn')
    page.wait_for_timeout(5000)
    page.screenshot(path='/workspace/02_query_result.png')
    print("截图: 02_query_result.png")

    result_div = page.query_selector('#resultDiv')
    if result_div:
        is_hidden = result_div.get_attribute('class')
        print(f"结果区 class: {is_hidden}")

    important_html = page.query_selector('#importantInfo')
    if important_html:
        text = important_html.inner_text()
        print(f"概览内容（前200字）: {text[:200]}")

    if page_errors:
        print("查询时页面错误:")
        for e in page_errors:
            print(f"  {e}")
    if console_logs:
        print("查询时控制台日志:")
        for log in console_logs:
            print(f"  {log}")

    print("\n=== 4. 检查原始 RDAP 信息 ===")
    original = page.query_selector('#originalInfoContent')
    if original:
        text = original.inner_text()
        print(f"原始信息（前300字）: {text[:300]}")
    else:
        print("未找到 #originalInfoContent")

    print("\n=== 5. 测试暗黑模式切换 ===")
    page.click('#themeToggle')
    page.wait_for_timeout(500)
    page.screenshot(path='/workspace/03_dark_mode.png')
    is_dark = page.evaluate('document.documentElement.classList.contains("dark")')
    print(f"暗黑模式已启用: {is_dark}")

    print("\n=== 6. 测试语言切换（英文）===")
    page.select_option('#languageSelect', 'en')
    page.wait_for_timeout(500)
    title_text = page.query_selector('h1').inner_text()
    submit_text = page.query_selector('#submit-btn span').inner_text()
    print(f"标题: {title_text}, 按钮文字: {submit_text}")
    page.screenshot(path='/workspace/04_english.png')

    print("\n=== 7. 测试移动端视图 ===")
    page.set_viewport_size({"width": 375, "height": 812})
    page.wait_for_timeout(500)
    page.screenshot(path='/workspace/05_mobile.png')
    fab_display = page.evaluate("getComputedStyle(document.querySelector('#fabHistory')).display")
    print(f"移动端 FAB display: {fab_display}")

    print("\n=== 8. 测试移动端历史抽屉 ===")
    page.click('#fabHistory')
    page.wait_for_timeout(800)
    page.screenshot(path='/workspace/06_mobile_drawer.png')
    drawer_open = page.evaluate("document.querySelector('#historyDrawer').classList.contains('open')")
    print(f"抽屉已打开: {drawer_open}")
    page.click('#closeDrawerBtn')
    page.wait_for_timeout(500)

    print("\n=== 9. 切回桌面，测试无 RDAP 服务的后缀 ===")
    page.set_viewport_size({"width": 1280, "height": 900})
    page.select_option('#languageSelect', 'zh-CN')
    page.wait_for_timeout(300)
    console_logs.clear()
    page_errors.clear()
    page.fill('#domain', 'test.invalidtld')
    page.click('#submit-btn')
    page.wait_for_timeout(4000)
    page.screenshot(path='/workspace/07_fallback.png')
    important_html = page.query_selector('#importantInfo')
    if important_html:
        text = important_html.inner_text()
        print(f"fallback 内容: {text[:300]}")
    if page_errors:
        print("fallback 页面错误:")
        for e in page_errors:
            print(f"  {e}")

    print("\n=== 10. 测试 IP 查询 ===")
    console_logs.clear()
    page_errors.clear()
    page.fill('#domain', '8.8.8.8')
    page.click('#submit-btn')
    page.wait_for_timeout(5000)
    page.screenshot(path='/workspace/08_ip_query.png')
    important_html = page.query_selector('#importantInfo')
    if important_html:
        text = important_html.inner_text()
        print(f"IP 查询结果（前200字）: {text[:200]}")
    if page_errors:
        print("IP 查询页面错误:")
        for e in page_errors:
            print(f"  {e}")
    if console_logs:
        print("IP 查询控制台日志:")
        for log in console_logs:
            print(f"  {log}")

    print("\n=== 调试完成 ===")
    browser.close()
