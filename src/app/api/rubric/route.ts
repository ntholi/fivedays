import prisma from '@/lib/prisma';
import { NextResponse } from 'next/server';

type PostData = {
  courseId: string;
  courseWorkId: string;
  title: string;
  points: number;
  description?: string;
};

export async function POST(request: Request) {
  const data = (await request.json()) as PostData;

  const rubric = await prisma.rubric.upsert({
    where: {
      courseWorkId: data.courseWorkId,
    },
    create: {
      courseId: data.courseId,
      courseWorkId: data.courseWorkId,
    },
    update: {},
  });

  const rubricCriteria = await prisma.rubricCriterion.create({
    data: {
      rubricId: rubric.id,
      title: data.title,
      description: data.description,
      points: data.points,
    },
  });

  return NextResponse.json({ rubricCriteria });
}
