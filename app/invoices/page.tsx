"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Plus, Search, Edit, Trash2, Eye, Download } from "lucide-react"

interface Invoice {
  id: string
  supplier: string
  amount: string
  status: "paid" | "pending" | "overdue"
  date: string
  dueDate: string
  description: string
}

export default function InvoicesPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const [statusFilter, setStatusFilter] = useState("all")
  const [isDialogOpen, setIsDialogOpen] = useState(false)

  const invoices: Invoice[] = [
    {
      id: "INV-001",
      supplier: "Công ty TNHH ABC",
      amount: "500,000 VNĐ",
      status: "paid",
      date: "2024-01-15",
      dueDate: "2024-01-30",
      description: "Dịch vụ vận chuyển tháng 1",
    },
    {
      id: "INV-002",
      supplier: "Công ty XYZ",
      amount: "750,000 VNĐ",
      status: "pending",
      date: "2024-01-14",
      dueDate: "2024-01-29",
      description: "Bảo trì thiết bị máy móc",
    },
    {
      id: "INV-003",
      supplier: "Nhà cung cấp DEF",
      amount: "1,200,000 VNĐ",
      status: "overdue",
      date: "2024-01-10",
      dueDate: "2024-01-25",
      description: "Tư vấn IT và phát triển phần mềm",
    },
    {
      id: "INV-004",
      supplier: "Công ty TNHH ABC",
      amount: "300,000 VNĐ",
      status: "pending",
      date: "2024-01-20",
      dueDate: "2024-02-05",
      description: "Vận chuyển hàng hóa đặc biệt",
    },
  ]

  const filteredInvoices = invoices.filter((invoice) => {
    const matchesSearch =
      invoice.id.toLowerCase().includes(searchTerm.toLowerCase()) ||
      invoice.supplier.toLowerCase().includes(searchTerm.toLowerCase()) ||
      invoice.description.toLowerCase().includes(searchTerm.toLowerCase())
    const matchesStatus = statusFilter === "all" || invoice.status === statusFilter
    return matchesSearch && matchesStatus
  })

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "paid":
        return <Badge className="bg-green-100 text-green-800">Đã thanh toán</Badge>
      case "pending":
        return <Badge className="bg-yellow-100 text-yellow-800">Chờ thanh toán</Badge>
      case "overdue":
        return <Badge className="bg-red-100 text-red-800">Quá hạn</Badge>
      default:
        return <Badge variant="secondary">{status}</Badge>
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold">Quản lý hóa đơn</h1>
          <p className="text-muted-foreground">Theo dõi và quản lý hóa đơn từ nhà cung cấp</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              Tạo hóa đơn
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-2xl">
            <DialogHeader>
              <DialogTitle>Tạo hóa đơn mới</DialogTitle>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="supplier">Nhà cung cấp</Label>
                  <Select>
                    <SelectTrigger>
                      <SelectValue placeholder="Chọn nhà cung cấp" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="abc">Công ty TNHH ABC</SelectItem>
                      <SelectItem value="xyz">Công ty XYZ</SelectItem>
                      <SelectItem value="def">Nhà cung cấp DEF</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="amount">Số tiền</Label>
                  <Input id="amount" placeholder="Nhập số tiền" />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="date">Ngày tạo</Label>
                  <Input id="date" type="date" />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="dueDate">Ngày đến hạn</Label>
                  <Input id="dueDate" type="date" />
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="description">Mô tả</Label>
                <Input id="description" placeholder="Mô tả hóa đơn" />
              </div>
              <div className="flex justify-end space-x-2">
                <Button variant="outline" onClick={() => setIsDialogOpen(false)}>
                  Hủy
                </Button>
                <Button onClick={() => setIsDialogOpen(false)}>Tạo hóa đơn</Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Danh sách hóa đơn</CardTitle>
          <div className="flex space-x-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
              <Input
                placeholder="Tìm kiếm theo mã, nhà cung cấp, mô tả..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10"
              />
            </div>
            <Select value={statusFilter} onValueChange={setStatusFilter}>
              <SelectTrigger className="w-48">
                <SelectValue placeholder="Lọc theo trạng thái" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Tất cả</SelectItem>
                <SelectItem value="paid">Đã thanh toán</SelectItem>
                <SelectItem value="pending">Chờ thanh toán</SelectItem>
                <SelectItem value="overdue">Quá hạn</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Mã hóa đơn</TableHead>
                <TableHead>Nhà cung cấp</TableHead>
                <TableHead>Số tiền</TableHead>
                <TableHead>Ngày tạo</TableHead>
                <TableHead>Ngày đến hạn</TableHead>
                <TableHead>Trạng thái</TableHead>
                <TableHead>Thao tác</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredInvoices.map((invoice) => (
                <TableRow key={invoice.id}>
                  <TableCell className="font-medium">{invoice.id}</TableCell>
                  <TableCell>{invoice.supplier}</TableCell>
                  <TableCell className="font-medium">{invoice.amount}</TableCell>
                  <TableCell>{invoice.date}</TableCell>
                  <TableCell>{invoice.dueDate}</TableCell>
                  <TableCell>{getStatusBadge(invoice.status)}</TableCell>
                  <TableCell>
                    <div className="flex space-x-2">
                      <Button variant="ghost" size="icon">
                        <Eye className="h-4 w-4" />
                      </Button>
                      <Button variant="ghost" size="icon">
                        <Download className="h-4 w-4" />
                      </Button>
                      <Button variant="ghost" size="icon">
                        <Edit className="h-4 w-4" />
                      </Button>
                      <Button variant="ghost" size="icon">
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}
