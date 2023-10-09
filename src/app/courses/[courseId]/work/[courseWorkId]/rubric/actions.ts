'use server';
import prisma from '@/lib/db';
import { revalidatePath } from 'next/cache';

export async function addRubricItem(
  courseId: string,
  courseWorkId: string,
  data: FormData
) {
  const title = data.get('title') as string;
  const description = data.get('description') as string;
  const points = Number(data.get('points'));

  await prisma.rubricItem.create({
    data: {
      rubric: {
        connectOrCreate: {
          where: {
            courseId_courseWorkId: {
              courseId: courseId,
              courseWorkId: courseWorkId,
            },
          },
          create: {
            courseId: courseId,
            courseWorkId: courseWorkId,
          },
        },
      },
      title: title,
      description: description,
      points: points,
    },
  });

  revalidatePath(`/courses/${courseId}/work/${courseWorkId}/rubric`);
}
