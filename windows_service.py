#!/usr/bin/env python
# -*- coding: utf-8 -*-
# @Author: Comzyh
# @Date:   2015-06-09 18:10:46
# @Last Modified by:   Comzyh
# @Last Modified time: 2015-06-09 18:23:00
import win32serviceutil
import win32service
import win32event
import servicemanager
import lan_device_reporter


class LanDeviceReportService(win32serviceutil.ServiceFramework):
    _svc_name_ = "LDRS"
    _svc_display_name_ = "LanDeviceReportService"

    def __init__(self, args):
        win32serviceutil.ServiceFramework.__init__(self, args)
        # Create an event which we will use to wait on.
        # The "service stop" request will set this event.
        self.hWaitStop = win32event.CreateEvent(None, 0, 0, None)

    def SvcStop(self):
        # Before we do anything, tell the SCM we are starting the stop
        # process.
        self.ReportServiceStatus(win32service.SERVICE_STOP_PENDING)
        # And set my event.
        win32event.SetEvent(self.hWaitStop)
        # lan_device_reporter.stop_flag = True
        exit(0)

    def SvcDoRun(self):
        servicemanager.LogMsg(servicemanager.EVENTLOG_INFORMATION_TYPE,
                              servicemanager.PYS_SERVICE_STARTED,
                              (self._svc_name_, ''))
        # win32event.WaitForSingleObject(self.hWaitStop, win32event.INFINITE)
        lan_device_reporter.main()

if __name__ == '__main__':
    win32serviceutil.HandleCommandLine(LanDeviceReportService)
