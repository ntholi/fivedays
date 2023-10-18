import { errorToJSON, toJSON } from '@/lib/common';
import { completion, createCompletion } from '@/lib/completions';
import prisma from '@/lib/db';
import { revalidatePath } from 'next/cache';
import { NextResponse } from 'next/server';
import { z } from 'zod';

const schema = z.object({
  courseName: z.string(),
  courseworkTitle: z.string(),
  courseWorkDescription: z.string(),
});

const idSchema = z.object({
  courseId: z.string(),
  courseWorkId: z.string(),
});

export async function POST(request: Request) {
  const data = await request.json();
  const input = schema.parse(data);
  const { courseId, courseWorkId } = idSchema.parse(data);

  try {
    const chatCompletion = await createCompletion(
      completion({
        task: 'create_rubric',
        input: input,
        responseType: {
          rubric: [
            {
              title: 'string',
              description: 'string',
              points: 'number',
            },
          ],
        },
      })
    );

    const { content } = chatCompletion.choices[0].message;

    saveToDatabase(courseId, courseWorkId, toJSON(content).rubric);

    revalidatePath(`/courses/${courseId}/work/${courseWorkId}/rubric`);
    return Response.json({ revalidated: true, now: Date.now() });
  } catch (error) {
    console.error(error);
    return NextResponse.json(errorToJSON(error));
  }
}
async function saveToDatabase(
  courseId: string,
  courseWorkId: string,
  rubric: any
) {
  await prisma.rubric.create({
    data: {
      courseId: courseId,
      courseWorkId: courseWorkId,
      rubricItems: {
        create: rubric,
      },
    },
  });
}
